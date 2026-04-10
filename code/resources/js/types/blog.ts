export type BlogPostStatus = 'draft' | 'published' | 'archived';

export type BlogContentFormat = 'markdown' | 'rich_text';

export type BlogOption = {
    value: string;
    label: string;
};

export type BlogPublicPost = {
    id: number;
    slug: string;
    title: string;
    excerpt: string | null;
    tags: string[];
    sourceUrls: string[];
    coverImageUrl: string | null;
    publishedAt: string | null;
    author: string | null;
    bodyHtml?: string;
};

export type BlogAdminPostSummary = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    status: BlogPostStatus;
    statusLabel: string;
    contentFormat: BlogContentFormat;
    coverImageUrl: string | null;
    tags: string[];
    publishedAt: string | null;
    updatedAt: string | null;
    author: string | null;
    publicUrl: string | null;
};

export type BlogAdminPost = BlogAdminPostSummary & {
    bodySource: string;
    bodyHtml: string;
    coverImagePath: string | null;
    sourceUrls: string[];
};

export type BlogEditorPost = {
    id: number | null;
    title: string;
    slug: string;
    excerpt: string;
    status: BlogPostStatus;
    statusLabel: string;
    contentFormat: BlogContentFormat;
    bodySource: string;
    bodyHtml: string;
    coverImageUrl: string | null;
    coverImagePath: string | null;
    tags: string[];
    sourceUrls: string[];
    publishedAt: string | null;
    updatedAt: string | null;
    author: string | null;
    publicUrl: string | null;
};
