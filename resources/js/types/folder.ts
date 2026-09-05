export interface FolderAbility {
    create: boolean;
    update: boolean;
    delete: boolean;
    manage: boolean;
    view: boolean;
    upload_media: boolean;
    edit_media: boolean;
    delete_media: boolean;
}

export interface FolderItem {
    id: string;
    name: string;
    display_name?: string;
    owner_name?: string | null;
    owner_email?: string | null;
    type: 'master' | 'personal';
    parent_id: string | null;
    master_folder_id: string | null;
    is_locked: boolean;
    is_shared_with_workspace: boolean;
    sort_order: number;
    children_count?: number;
    medias_count?: number;
    posts_count?: number;
    can?: FolderAbility;
    owner?: { id: string; name: string; email: string } | null;
    permissions?: FolderPermissionItem[];
}

export interface FolderPermissionItem {
    id: string;
    user_id: string | null;
    team_id: string | null;
    permission: string;
    user?: { id: string; name: string; email: string } | null;
    team?: { id: string; name: string } | null;
}
