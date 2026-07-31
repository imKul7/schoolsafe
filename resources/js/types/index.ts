import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export type UserRole = 'super_admin' | 'school_admin' | 'gate_officer' | 'teacher' | 'parent';

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    [key: string]: unknown;
}

export interface User {
    id: number;
    school_id: number | null;
    name: string;
    email: string;
    role: UserRole;
    phone: string | null;
    is_active: boolean;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}
