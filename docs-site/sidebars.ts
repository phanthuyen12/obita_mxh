import type {SidebarsConfig} from '@docusaurus/plugin-content-docs';

const sidebars: SidebarsConfig = {
  tutorialSidebar: [
    {
      type: 'category',
      label: 'Tổng Quan Hệ Thống',
      collapsed: false,
      items: [
        'getting-started/overview',
        'getting-started/quickstart',
        'getting-started/architecture-flow',
      ],
    },
    {
      type: 'category',
      label: 'Phân Quyền & Vai Trò',
      collapsed: false,
      items: [
        'roles-and-permissions/overview',
        'roles-and-permissions/admin-guide',
        'roles-and-permissions/reviewer-guide',
        'roles-and-permissions/editor-guide',
      ],
    },
    {
      type: 'category',
      label: 'Quản Lý Nền Tảng & Kênh Kết Nối',
      items: [
        'social-platforms/connect-socials',
        'social-platforms/connect-ecommerce',
        'social-platforms/channel-assignments',
      ],
    },
    {
      type: 'category',
      label: 'Quản Lý Nội Dung & Xuất Bản',
      items: [
        'content-management/multi-composer',
        'content-management/visual-calendar',
        'content-management/approval-workflow',
        'content-management/templates-and-signatures',
        'content-management/asset-library',
      ],
    },
    {
      type: 'category',
      label: 'AI Copilot & Nhân Bản Nội Dung',
      items: [
        'ai-content/content-cloning',
        'ai-content/ai-post-wizard',
        'ai-content/brand-voice-dify',
      ],
    },
    {
      type: 'category',
      label: 'Hộp Thư Hợp Nhất & CSKH (Omnichat)',
      items: [
        'omnichat/unified-inbox',
        {
          type: 'category',
          label: 'Website Live Chat (CSKH)',
          items: [
            'omnichat/website-chat/overview',
            'omnichat/website-chat/admin-setup',
            'omnichat/website-chat/operations',
          ],
        },
        {
          type: 'category',
          label: 'Dành cho Đối Tác (API)',
          items: [
            'omnichat/website-chat/api-reference',
            'omnichat/website-chat/widget-integration',
            'omnichat/website-chat/technical-specification',
          ],
        },
        'omnichat/tags-and-routing',
        'omnichat/leads-crm',
        'omnichat/analytics',
      ],
    },
    {
      type: 'category',
      label: 'Tự Động Hóa Quy Trình (Automations)',
      items: [
        'automations/visual-builder',
        'automations/rss-webhook',
      ],
    },
    {
      type: 'category',
      label: 'Báo Cáo & Phân Tích Dữ Liệu',
      items: [
        'analytics/social-performance',
        'analytics/export-data',
      ],
    },
    {
      type: 'category',
      label: 'Quản Trị Hệ Thống & Cài Đặt',
      items: [
        'system-admin/workspaces',
        'system-admin/billing',
        'system-admin/api-and-mcp',
      ],
    },
  ],
};

export default sidebars;
