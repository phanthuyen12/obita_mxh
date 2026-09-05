import {themes as prismThemes} from 'prism-react-renderer';
import type {Config} from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';

const config: Config = {
  title: 'King Hub Social',
  tagline: 'Tài liệu hướng dẫn sử dụng Hệ thống Quản trị Đa Mạng Xã Hội, AI Content Clones & CSKH Hợp Nhất Omnichat',
  favicon: 'img/favicon.ico',

  future: {
    v4: true,
  },

  url: 'https://docs.kinghub.social',
  baseUrl: '/',

  organizationName: 'king-hub',
  projectName: 'king-hub-social-docs',

  onBrokenLinks: 'warn',

  i18n: {
    defaultLocale: 'vi',
    locales: ['vi'],
  },

  markdown: {
    mermaid: true,
  },
  themes: ['@docusaurus/theme-mermaid'],

  presets: [
    [
      'classic',
      {
        docs: {
          sidebarPath: './sidebars.ts',
          routeBasePath: '/', // Serve docs at root
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      } satisfies Preset.Options,
    ],
  ],

  themeConfig: {
    image: 'img/logoKING.png',
    colorMode: {
      defaultMode: 'light',
      respectPrefersColorScheme: true,
    },
    navbar: {
      title: 'King Hub Social Docs',
      logo: {
        alt: 'King Hub Logo',
        src: 'img/logo.svg',
      },
      items: [
        {
          type: 'docSidebar',
          sidebarId: 'tutorialSidebar',
          position: 'left',
          label: 'Tài Liệu Hướng Dẫn',
        },
        {
          to: '/roles-and-permissions/overview',
          position: 'left',
          label: 'Phân Quyền & Vai Trò',
        },
        {
          to: '/content-management/multi-composer',
          position: 'left',
          label: 'Đăng Bài & Lịch Xuất Bản',
        },
        {
          to: '/ai-content/content-cloning',
          position: 'left',
          label: 'Content Clones AI',
        },
        {
          to: '/omnichat/unified-inbox',
          position: 'left',
          label: 'Hộp Thư Omnichat',
        },
        {
          to: '/omnichat/website-chat/api-reference',
          position: 'right',
          label: 'API & Developers',
        },
      ],
    },
    footer: {
      style: 'dark',
      links: [
        {
          title: 'Tài Liệu Hướng Dẫn',
          items: [
            {
              label: 'Tổng Quan Hệ Thống',
              to: '/',
            },
            {
              label: 'Soạn Thảo & Lập Lịch',
              to: '/content-management/multi-composer',
            },
            {
              label: 'Chiến Dịch Content Clones',
              to: '/ai-content/content-cloning',
            },
            {
              label: 'Hộp Thư Hợp Nhất Omnichat',
              to: '/omnichat/unified-inbox',
            },
          ],
        },
        {
          title: 'Phân Quyền & Vận Hành',
          items: [
            {
              label: 'Hướng Dẫn Quản Trị Viên (Admin)',
              to: '/roles-and-permissions/admin-guide',
            },
            {
              label: 'Hướng Dẫn Người Kiểm Duyệt (Reviewer)',
              to: '/roles-and-permissions/reviewer-guide',
            },
            {
              label: 'Hướng Dẫn Chuyên Viên Nội Dung (Editor)',
              to: '/roles-and-permissions/editor-guide',
            },
            {
              label: 'Quy Trình Duyệt Bài Viết',
              to: '/content-management/approval-workflow',
            },
          ],
        },
      ],
      copyright: `Bản quyền © ${new Date().getFullYear()} King Hub Social. Tài liệu hướng dẫn sử dụng chính thức.`,
    },
    prism: {
      theme: prismThemes.github,
      darkTheme: prismThemes.dracula,
    },
  } satisfies Preset.ThemeConfig,
};

export default config;
