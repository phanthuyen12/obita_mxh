export interface Attachment {
  name: string
  size?: string
  type: 'image' | 'file'
  url?: string
}

export interface Message {
  id: string
  sender: 'me' | 'other'
  text?: string
  time: string
  isRead?: boolean
  attachment?: Attachment
}

export type ConversationTag = 'VIP' | 'Chốt đơn' | 'Đang tư vấn' | 'Cần hỗ trợ' | 'Khách mới' | 'Tiềm năng'

export type ChannelSource = 'facebook' | 'telegram' | 'zalo' | 'website'

export interface ChatItem {
  id: string
  name: string
  channelSource?: ChannelSource
  channelName?: string
  phone?: string
  tags?: ConversationTag[]
  folderCategory?: string[]
  avatarType: 'text' | 'image' | 'icon'
  avatarText?: string
  avatarBg?: string
  statusText?: string
  time: string
  isPinned?: boolean
  isMuted?: boolean
  unreadCount?: string | number
  unreadType?: 'blue' | 'gray'
  lastMessage: {
    sender?: string
    prefixIcon?: string
    text: string
    isDraft?: boolean
  }
  messages?: Message[]
}
