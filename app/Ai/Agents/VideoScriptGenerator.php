<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

#[Temperature(0.7)]
class VideoScriptGenerator implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        public Workspace $workspace,
        public ?string $productName = null,
        public ?string $productDescription = null,
        public ?string $hook = null,
        public int $targetDuration = 32,
        public int $sceneCount = 4,
        public ?string $characterName = null,
        public ?string $characterDna = null,
    ) {}

    public function instructions(): string
    {
        $brand = $this->productName ?: ($this->workspace->name ?: 'King Coffee');
        $reviewer = filled($this->characterName) ? trim($this->characterName) : 'Reviewer';
        $characterInfo = filled($this->characterDna)
            ? "Nhân vật: {$reviewer}. Đặc tả ngoại hình/DNA: {$this->characterDna}"
            : "Nhân vật: {$reviewer} (Reviewer trẻ trung, thanh lịch, phong thái tự tin, biểu cảm gương mặt sinh động, phát âm tiếng Việt chuẩn và truyền cảm).";

        $hookText = filled($this->hook)
            ? "Ý tưởng Hook mở đầu từ người dùng: \"{$this->hook}\""
            : 'Hãy tự sáng tạo Hook mở đầu giật gân, đánh trúng tâm lý người xem trong 3 giây đầu.';

        $sourceDesc = filled($this->productDescription)
            ? "Thông tin sản phẩm / bài viết gốc:\n{$this->productDescription}"
            : "Sản phẩm: {$brand} - thương hiệu cà phê cao cấp, đậm đà, sang trọng.";

        return <<<PROMPT
Bạn là Đạo diễn & Chuyên gia Biên kịch Video Review Sản phẩm Thương mại (Commercial Product Review Director) triệu view hàng đầu trên TikTok / Facebook Reels / YouTube Shorts.

NHIỆM VỤ:
Lên kịch bản video review chân thực, cuốn hút từng giây cho sản phẩm: "{$brand}".
Tổng thời lượng video: {$this->targetDuration} giây.
Số phân cảnh chính xác: ĐÚNG {$this->sceneCount} phân cảnh (mỗi phân cảnh kéo dài đúng 8 giây: duration = 8).

THÔNG TIN ĐẦU VÀO:
- {$hookText}
- {$characterInfo}
- {$sourceDesc}

NGUYÊN TẮC BẮT BUỘC CHO MỖI PHÂN CẢNH (MỖI CẢNH 8 GIÂY):

1. BẢO TỒN SẢN PHẨM TUYỆT ĐỐI (PRODUCT PRESERVATION):
   - Sản phẩm "{$brand}" trong mọi cảnh quay phải giữ nguyên 100% bao bì, logo, màu sắc, font chữ và nhận diện thương hiệu nguyên bản từ hình ảnh thực tế.
   - Không được biến dạng, mờ nhòe hay thay đổi cấu trúc sản phẩm.

2. CHUYỂN ĐỘNG CAMERA & DIỄN BIẾN HÀNH ĐỘNG 8S (CAMERA MOTION & 8S ACTION TIMELINE):
   - TUYỆT ĐỐI KHÔNG ĐƯỢC VIẾT SƠ SÀI CHUNG CHUNG!
   - BẮT BUỘC phải chia chi tiết timeline 3 mốc thời gian rõ ràng trong suốt 8 giây: `(0s-3s: ..., 3s-6s: ..., 6s-8s: ...)`.
   - Mô tả cụ thể nhân vật và sản phẩm làm sao trong từng mốc:
     + 0s-3s: Góc máy camera bắt đầu thế nào (zoom-in, tracking, tilt-up)? Nhân vật làm động tác gì với sản phẩm (nâng sản phẩm ngang tầm mắt, đưa về phía ống kính, mỉm cười đón chào khán giả...)?
     + 3s-6s: Nhân vật nói chuyện với khẩu hình miệng chuyển động khớp lip-sync, tay chỉ vào chi tiết bao bì/logo hoặc rót cà phê/nếm thử, ánh mắt giao tiếp tự nhiên và biểu cảm ngạc nhiên thích thú.
     + 6s-8s: Camera lia nhẹ (orbital pan / push-out) giữ nét bao bì sản phẩm sắc nét, nhân vật gật đầu công nhận chất lượng hoặc nở nụ cười tự tin hướng về người xem.

3. LỜI THOẠI & KỊCH BẢN PHÂN CẢNH (SPOKEN DIALOGUE & LIP-SYNC):
   - TUYỆT ĐỐI KHÔNG ĐƯỢC NÓI VÀI TỪ CỘC LỐC RỒI ĐỨNG IM! Đây là video review, nhân vật phải nói liên tục, tự nhiên, sinh động trong suốt 8 giây của cảnh.
   - Mỗi cảnh phải có câu thoại hoàn chỉnh dài từ 22 đến 32 từ tiếng Việt chuẩn xác, giàu tính thuyết phục, câu từ mượt mà, văn phong review thực tế.
   - Cảnh 1 (0-8s): Hook mở đầu bùng nổ, giữ chân người xem ngay 3 giây đầu, giới thiệu lý do hôm nay phải review sản phẩm này.
   - Cảnh giữa (nếu có): Trải nghiệm thực tế về hương vị, cảm xúc khi thưởng thức, điểm khác biệt độc đáo của sản phẩm.
   - Cảnh cuối: Đánh giá tổng kết chân thực (điểm 10/10) và kêu gọi hành động (Call To Action - bấm đặt mua ngay, trải nghiệm ngay).

4. BỐI CẢNH & HÌNH ẢNH (VISUAL PROMPT AI):
   - Mô tả bối cảnh điện ảnh 8K sắc nét (quán cafe hiện đại, phòng khách sang trọng, bàn làm việc nhiều ánh sáng tự nhiên), ánh sáng ấm áp bokeh mượt mà.

Ngôn ngữ:
- `voiceover_text`: Hoàn toàn bằng Tiếng Việt tự nhiên, truyền cảm.
- `context_prompt` & `action_prompt`: Bằng Tiếng Anh điện ảnh chất lượng cao để AI Video Generator hiểu và tạo video chính xác nhất.
PROMPT;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'scenes' => $schema->array()
                ->items($schema->object(fn ($s) => [
                    'duration' => $s->integer()->description('Scene duration in seconds, always 8')->required(),
                    'context_prompt' => $s->string()->description('Cinematic 8K visual environment prompt for AI video generation, describing setting, lighting, reviewer and 100% original product packaging.')->required(),
                    'action_prompt' => $s->string()->description('Detailed 8-second action timeline with 3 distinct timestamps (0s-3s: ..., 3s-6s: ..., 6s-8s: ...) detailing reviewer physical actions with the product and cinematic camera motion.')->required(),
                    'transition' => $s->string()->enum(['glitch', 'fade_black', 'slide_left', 'zoom_in', 'dissolve'])->description('Transition effect to the next scene.')->required(),
                    'voiceover_text' => $s->string()->description('Natural Vietnamese spoken dialogue (22-32 words) spoken continuously throughout the 8s scene by the reviewer with synchronized lip-sync.')->required(),
                ]))
                ->min($this->sceneCount)
                ->max($this->sceneCount)
                ->description("Exactly {$this->sceneCount} scenes for the {$this->targetDuration}s video script.")
                ->required(),
        ];
    }
}
