export type AdminDashboardStats = {
    total: number;
    verified: number;
    unverified: number;
    registeredLast7Days: number;
};

// 管理画面の会員一覧に渡される会員の情報（パスワードなどは含まない）
export type AdminUserRow = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    created_at: string | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

// Laravel の paginate() が返す形のうち、画面で使うもの
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: PaginationLink[];
};
