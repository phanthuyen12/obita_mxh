<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Omnichat;

use App\Enums\Omnichat\ChannelProvider;
use App\Http\Controllers\App\Controller;
use App\Models\AiBot;
use App\Models\AiSaleBotKnowledge;
use App\Models\AiSaleBotProduct;
use App\Models\OmnichatChannel;
use App\Models\SocialAccount;
use App\Services\Dify\DifyChatClient;
use App\Services\Dify\DifyKnowledgeClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AiTrainController extends Controller
{
    public function __construct(
        protected DifyChatClient $difyChatClient,
        protected DifyKnowledgeClient $difyKnowledgeClient,
    ) {}

    /**
     * Hiển thị giao diện chính Huấn luyện Bot AI Sale.
     */
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(
            $workspace && ($request->user()->isAccountOwner() || $request->user()->can('manageTeam', $workspace)),
            SymfonyResponse::HTTP_FORBIDDEN
        );

        $knowledges = AiSaleBotKnowledge::query()
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->get();

        $products = AiSaleBotProduct::query()
            ->where('workspace_id', $workspace->id)
            ->latest()
            ->paginate(15);

        $bots = AiBot::query()
            ->where('workspace_id', $workspace->id)
            ->orderByDesc('is_default')
            ->get()
            ->map(fn (AiBot $bot) => [
                'id' => $bot->id,
                'name' => $bot->name,
                'persona_tone' => $bot->persona_tone ?? 'friendly',
                'bot_role' => $bot->bot_role ?? 'sales_consultant',
                'greeting_message' => $bot->greeting_message,
                'system_prompt' => $bot->system_prompt,
                'objection_rules' => $bot->objection_rules ?? [],
                'dify_base_url' => $bot->dify_base_url,
                'dify_dataset_id' => $bot->dify_dataset_id,
                'dataset_key_set' => filled($bot->dify_dataset_api_key),
                'key_set' => filled($bot->dify_api_key),
                'is_active' => $bot->is_active,
                'is_default' => $bot->is_default,
            ]);

        // Danh sách Fanpage Facebook / Instagram / Telegram / Website để gắn Bot AI trả lời tự động
        $channels = collect();

        SocialAccount::query()
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->whereIn('platform', [
                \App\Enums\SocialAccount\Platform::Facebook,
                \App\Enums\SocialAccount\Platform::Instagram,
                \App\Enums\SocialAccount\Platform::InstagramFacebook,
            ])
            ->get()
            ->each(fn (SocialAccount $account) => $channels->push([
                'id' => $account->id,
                'name' => $account->display_label,
                'provider' => $account->platform->network(),
                'type' => 'social_account',
                'ai_enabled' => (bool) data_get($account->meta, 'ai_care.enabled', false),
                'bot_id' => data_get($account->meta, 'ai_care.bot_id'),
            ]));

        OmnichatChannel::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('provider', [ChannelProvider::Telegram, ChannelProvider::Website])
            ->get()
            ->each(fn (OmnichatChannel $ch) => $channels->push([
                'id' => $ch->id,
                'name' => $ch->name,
                'provider' => $ch->provider->value,
                'type' => 'channel',
                'ai_enabled' => (bool) data_get($ch->settings, 'ai_care.enabled', false),
                'bot_id' => data_get($ch->settings, 'ai_care.bot_id'),
            ]));

        return Inertia::render('omnichat/AiTrain', [
            'knowledges' => $knowledges,
            'products' => $products,
            'bots' => $bots,
            'channels' => $channels->values(),
            'workspaceId' => $workspace->id,
        ]);
    }

    /**
     * Thử kết nối Dify API.
     */
    public function testDify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dify_api_key' => ['nullable', 'string'],
            'dify_base_url' => ['nullable', 'string'],
            'bot_id' => ['nullable', 'uuid'],
        ]);

        $workspace = $request->user()->currentWorkspace;
        $apiKey = $validated['dify_api_key'] ?? null;
        $baseUrl = $validated['dify_base_url'] ?? null;

        if (empty($apiKey) && ! empty($validated['bot_id'])) {
            $bot = AiBot::query()->where('workspace_id', $workspace->id)->whereKey($validated['bot_id'])->first();
            if ($bot) {
                $apiKey = $bot->dify_api_key;
                $baseUrl = $baseUrl ?: $bot->dify_base_url;
            }
        }

        $res = $this->difyChatClient->testConnection($apiKey, $baseUrl);

        return response()->json($res);
    }

    /**
     * Upload tài liệu tri thức (PDF, DOCX, TXT hoặc Hình ảnh: PNG, JPG, WEBP)
     * và tự động đồng bộ sang Dify Knowledge Base (Dataset).
     */
    public function storeKnowledge(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'mimes:pdf,docx,doc,txt,png,jpg,jpeg,webp,gif,csv', 'max:15360'],
            'bot_id' => ['nullable', 'uuid'],
        ]);

        $filePath = null;
        $fileType = 'manual_text';
        $content = $validated['content'] ?? '';
        $difyDocumentId = null;
        $difyBatchId = null;
        $difySynced = false;
        $syncMessage = null;

        // Xác định bot cấu hình Dify
        $bot = null;
        if (! empty($validated['bot_id'])) {
            $bot = AiBot::query()->where('workspace_id', $workspace->id)->whereKey($validated['bot_id'])->first();
        } else {
            $bot = AiBot::defaultFor($workspace->id);
        }

        $datasetId = $bot?->dify_dataset_id;
        $datasetApiKey = $bot?->dify_dataset_api_key ?: $bot?->dify_api_key;
        $baseUrl = $bot?->dify_base_url ?: 'https://kingai.tnicorporation.com/v1';

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $fileType = strtolower($file->getClientOriginalExtension());
            $filePath = $file->store('ai-knowledges', 'public');

            // Nếu là file txt, đọc trực tiếp content
            if ($fileType === 'txt') {
                $content = (string) file_get_contents($file->getRealPath());
            }

            // Đẩy file lên Dify Knowledge Base nếu bot có dataset_id
            if ($datasetId && $datasetApiKey) {
                try {
                    $difyRes = $this->difyKnowledgeClient->createDocumentByFile(
                        datasetId: $datasetId,
                        file: $file,
                        apiKey: $datasetApiKey,
                        baseUrl: $baseUrl,
                        customName: $validated['title'].'.'.$fileType,
                    );
                    $difyDocumentId = $difyRes['document']['id'] ?? null;
                    $difyBatchId = $difyRes['batch'] ?? null;
                    $difySynced = true;
                } catch (\Throwable $e) {
                    $syncMessage = 'Lưu trữ cục bộ thành công, đồng bộ Dify Knowledge thất bại: '.$e->getMessage();
                }
            }
        } elseif (! empty($content)) {
            // Đẩy văn bản trực tiếp lên Dify Knowledge Base
            if ($datasetId && $datasetApiKey) {
                try {
                    $difyRes = $this->difyKnowledgeClient->createDocumentByText(
                        datasetId: $datasetId,
                        name: $validated['title'].'.txt',
                        text: $content,
                        apiKey: $datasetApiKey,
                        baseUrl: $baseUrl,
                    );
                    $difyDocumentId = $difyRes['document']['id'] ?? null;
                    $difyBatchId = $difyRes['batch'] ?? null;
                    $difySynced = true;
                } catch (\Throwable $e) {
                    $syncMessage = 'Lưu trữ cục bộ thành công, đồng bộ Dify Knowledge thất bại: '.$e->getMessage();
                }
            }
        }

        $knowledge = AiSaleBotKnowledge::query()->create([
            'workspace_id' => $workspace->id,
            'ai_bot_id' => $validated['bot_id'] ?? null,
            'title' => $validated['title'],
            'file_type' => $fileType,
            'file_path' => $filePath,
            'dify_document_id' => $difyDocumentId,
            'dify_batch_id' => $difyBatchId,
            'content' => $content,
            'token_count' => mb_strlen((string) $content),
            'status' => 'ready',
        ]);

        return response()->json([
            'success' => true,
            'knowledge' => $knowledge,
            'dify_synced' => $difySynced,
            'dify_document_id' => $difyDocumentId,
            'message' => $difySynced
                ? 'Đã nạp tri thức thành công và tự động đồng bộ sang Dify Knowledge Base!'
                : ($syncMessage ?: 'Đã lưu tri thức thành công vào hệ thống!'),
        ]);
    }

    /**
     * Xóa tài liệu tri thức (và xóa khỏi Dify Knowledge nếu có).
     */
    public function destroyKnowledge(Request $request, AiSaleBotKnowledge $knowledge): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless($knowledge->workspace_id === $workspace->id, 404);

        // Xóa trên Dify nếu có
        if ($knowledge->dify_document_id && $knowledge->bot) {
            $bot = $knowledge->bot;
            $datasetId = $bot->dify_dataset_id;
            $datasetApiKey = $bot->dify_dataset_api_key ?: $bot->dify_api_key;
            $baseUrl = $bot->dify_base_url ?: 'https://kingai.tnicorporation.com/v1';

            if ($datasetId && $datasetApiKey) {
                try {
                    $this->difyKnowledgeClient->deleteDocument(
                        datasetId: $datasetId,
                        documentId: $knowledge->dify_document_id,
                        apiKey: $datasetApiKey,
                        baseUrl: $baseUrl,
                    );
                } catch (\Throwable) {
                    // Ignore dify deletion failure
                }
            }
        }

        $knowledge->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa tài liệu tri thức thành công!',
        ]);
    }

    /**
     * Thêm sản phẩm / Bảng giá mới.
     */
    public function storeProduct(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
        ]);

        $product = AiSaleBotProduct::query()->create([
            'workspace_id' => $workspace->id,
            ...$validated,
            'is_in_stock' => ($validated['stock_quantity'] ?? 0) > 0,
        ]);

        return response()->json([
            'success' => true,
            'product' => $product,
        ]);
    }

    /**
     * Chat Sandbox thử nghiệm với Bot Sale.
     */
    public function chatSandbox(Request $request): JsonResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $validated = $request->validate([
            'message' => ['required', 'string'],
            'bot_id' => ['nullable', 'uuid'],
        ]);

        $userMessage = trim($validated['message']);
        $lowerMsg = mb_strtolower($userMessage);

        $selectedBot = null;
        if (! empty($validated['bot_id'])) {
            $selectedBot = AiBot::query()->where('workspace_id', $workspace->id)->whereKey($validated['bot_id'])->first();
        } else {
            $selectedBot = AiBot::defaultFor($workspace->id);
        }

        // If a Dify bot is selected and has an API key, try sending to Dify
        if ($selectedBot && filled($selectedBot->dify_api_key)) {
            try {
                $difyRes = $this->difyChatClient->sendMessage(
                    query: $userMessage,
                    user: 'sandbox-user-'.$request->user()->id,
                    apiKey: $selectedBot->dify_api_key,
                    baseUrl: $selectedBot->dify_base_url ?: 'https://kingai.tnicorporation.com/v1',
                );

                if (! empty($difyRes['answer'])) {
                    return response()->json([
                        'success' => true,
                        'reply' => $difyRes['answer'],
                        'source' => 'Dify Bot: '.$selectedBot->name,
                    ]);
                }
            } catch (\Throwable $e) {
                // If Dify fails or isn't reachable, fall back smoothly to local RAG
            }
        }

        // 1. Kiểm tra Sản phẩm & Bảng giá
        $matchedProduct = AiSaleBotProduct::query()
            ->where('workspace_id', $workspace->id)
            ->where(function ($q) use ($userMessage): void {
                $q->where('name', 'like', "%{$userMessage}%")
                    ->orWhere('sku', 'like', "%{$userMessage}%");
            })
            ->first();

        // 2. Kiểm tra Tri thức & Tài liệu đã nạp (RAG context search)
        $matchedKnowledge = AiSaleBotKnowledge::query()
            ->where('workspace_id', $workspace->id)
            ->where('status', 'ready')
            ->where(function ($q) use ($lowerMsg): void {
                $words = explode(' ', $lowerMsg);
                foreach (array_slice($words, 0, 4) as $w) {
                    if (mb_strlen($w) >= 3) {
                        $q->orWhere('content', 'like', "%{$w}%")
                            ->orWhere('title', 'like', "%{$w}%");
                    }
                }
            })
            ->first();

        $botName = $selectedBot?->name ?? 'AI Sale Assistant';
        $tone = $selectedBot?->persona_tone ?? 'friendly';
        $greetingPrefix = match ($tone) {
            'professional' => 'Kính chào quý khách, ',
            'enthusiastic' => 'Dạ tuyệt vời quá ạ! ',
            default => 'Dạ em chào anh/chị ạ! ',
        };

        $source = $selectedBot ? "Bot: {$selectedBot->name} (Local RAG)" : 'AI Sale Bot';
        $reply = '';

        if ($matchedProduct) {
            $formattedPrice = number_format((float) $matchedProduct->price, 0, ',', '.').'đ';
            $saleText = $matchedProduct->sale_price ? ' (Giá ưu đãi chỉ còn: '.number_format((float) $matchedProduct->sale_price, 0, ',', '.').'đ)' : '';
            $reply = "{$greetingPrefix}Bên em đang có sản phẩm **{$matchedProduct->name}** (Mã SKU: {$matchedProduct->sku}) với giá niêm yết là {$formattedPrice}{$saleText} ạ.\nHiện tại kho còn {$matchedProduct->stock_quantity} {$matchedProduct->unit}. Anh/chị có muốn em hỗ trợ lên đơn giữ suất ưu đãi này cho mình không ạ?";
            $source = 'Bảng giá: '.$matchedProduct->name;
        } elseif (str_contains($lowerMsg, 'giá') || str_contains($lowerMsg, 'bao nhiêu') || str_contains($lowerMsg, 'bảng giá')) {
            $allProducts = AiSaleBotProduct::query()->where('workspace_id', $workspace->id)->take(5)->get();
            if ($allProducts->isNotEmpty()) {
                $list = $allProducts->map(fn ($p) => "• **{$p->name}** ({$p->sku}): ".number_format((float) $p->price, 0, ',', '.').'đ'.($p->sale_price ? ' 🔥 Giảm còn: '.number_format((float) $p->sale_price, 0, ',', '.').'đ' : ''))->join("\n");
                $reply = "{$greetingPrefix}Gửi anh/chị tham khảo bảng giá các sản phẩm hiện có bên em ạ:\n\n{$list}\n\nAnh/chị đang cần đặt số lượng bao nhiêu hoặc giao tới khu vực nào để em báo phí ship/freeship tốt nhất ạ?";
                $source = 'Bảng giá sản phẩm';
            } else {
                $reply = "Dạ hiện tại shop chưa cập nhật sản phẩm nào vào bảng giá. Anh/chị hãy vào Tab '2. Bảng giá & Tồn kho' để thêm sản phẩm mẫu nhé!";
                $source = 'Hệ thống';
            }
        } elseif ($matchedKnowledge) {
            // Trích xuất đoạn tóm tắt kiến thức liên quan
            $contentSnippet = mb_substr((string) $matchedKnowledge->content, 0, 300);
            $reply = "{$greetingPrefix}Theo tài liệu **{$matchedKnowledge->title}** của shop:\n\n\"{$contentSnippet}...\"\n\nAnh/chị cần em hỗ trợ thêm thông tin chi tiết nào nữa không ạ?";
            $source = 'Tài liệu: '.$matchedKnowledge->title;
        } elseif (preg_match('/(0[3|5|7|8|9])+([0-9]{8})\b/', $userMessage, $phoneMatches)) {
            $reply = "Dạ em đã ghi nhận số điện thoại **{$phoneMatches[0]}** của anh/chị rồi ạ! Chuyên viên bên em sẽ liên hệ lại ngay để hỗ trợ chốt đơn và gửi quà tặng kèm cho mình nhé. Em cảm ơn anh/chị nhiều ạ! ❤️";
            $source = 'Kịch bản thu thập Lead (SĐT)';
        } elseif (str_contains($lowerMsg, 'đắt') || str_contains($lowerMsg, 'cao thế') || str_contains($lowerMsg, 'giảm giá')) {
            $reply = 'Dạ em hiểu là mức giá ban đầu có thể làm mình cân nhắc ạ. Nhưng sản phẩm bên em cam kết chính hãng 100%, bảo hành uy tín và được khách hàng đánh giá rất cao về độ bền/chất lượng. Nếu anh/chị đặt trong hôm nay, bên em sẽ tặng thêm mã giảm giá hoặc hỗ trợ phí vận chuyển cho mình ạ!';
            $source = 'Kịch bản xử lý từ chối (Chê đắt)';
        } else {
            $customPromptSnippet = $selectedBot?->greeting_message ?: "Em là {$botName}, trợ lý AI Sale chuyên viên tư vấn bán hàng của shop.";
            $reply = "{$greetingPrefix}{$customPromptSnippet}\nAnh/chị có thể hỏi em về **bảng giá sản phẩm**, **chính sách ưu đãi**, hoặc **gửi SĐT** để được hỗ trợ tốt nhất nhé!";
            $source = $botName;
        }

        return response()->json([
            'success' => true,
            'reply' => $reply,
            'source' => $source,
        ]);
    }
}
