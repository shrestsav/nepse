import type { BlogPublicPost } from '@/types';

const DEFAULT_CATEGORY = 'NEPSE';

export function relativeBlogDate(value: string | null | undefined): string {
    if (!value) {
        return 'Today';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    const diff = Math.floor((Date.now() - date.getTime()) / (1000 * 60 * 60 * 24));

    if (diff <= 0) {
        return 'Today';
    }

    if (diff === 1) {
        return 'Yesterday';
    }

    if (diff < 7) {
        return `${diff}d ago`;
    }

    if (diff < 30) {
        return `${Math.floor(diff / 7)}w ago`;
    }

    const months = Math.floor(diff / 30);

    return months === 1 ? '1mo ago' : `${months}mo ago`;
}

export function formatBlogDate(value: string | null | undefined): string {
    if (!value) {
        return 'Today';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

export function blogTagLabel(tag: string | null | undefined): string {
    return String(tag ?? '')
        .replace(/[-_]+/g, ' ')
        .replace(/\b\w/g, (match) => match.toUpperCase())
        .trim();
}

export function blogCategories(tags: string[] | null | undefined): string[] {
    const categories = (tags ?? [])
        .map(blogTagLabel)
        .filter(Boolean);

    return categories.length > 0 ? categories.slice(0, 2) : [DEFAULT_CATEGORY];
}

export function blogReadingTimeFromHtml(html: string | null | undefined): number {
    const words = stripHtml(html)
        .split(/\s+/)
        .filter(Boolean);

    return Math.max(4, Math.ceil(words.length / 220));
}

export function blogExcerpt(
    excerpt: string | null | undefined,
    html: string | null | undefined,
): string {
    if (excerpt?.trim()) {
        return excerpt.trim();
    }

    const plainText = stripHtml(html)
        .split(/\s+/)
        .filter(Boolean);

    return plainText.length > 0
        ? `${plainText.slice(0, 30).join(' ')}…`
        : 'Read the full article.';
}

export function blogCoverImage(
    post: Pick<BlogPublicPost, 'coverImageUrl' | 'slug' | 'title'>,
    wide = false,
): string {
    if (post.coverImageUrl) {
        return post.coverImageUrl;
    }

    const seed = encodeURIComponent(post.slug || post.title || 'nepse-blog');

    return wide
        ? `https://picsum.photos/seed/${seed}/1360/760`
        : `https://picsum.photos/seed/${seed}/840/530`;
}

export function joinBlogList(values: string[] | null | undefined): string {
    return (values ?? []).join('\n');
}

export function splitBlogList(value: string | string[] | null | undefined): string[] {
    if (Array.isArray(value)) {
        return value.map((item) => item.trim()).filter(Boolean);
    }

    if (!value) {
        return [];
    }

    return value
        .split(/[\r\n,]+/)
        .map((item) => item.trim())
        .filter(Boolean);
}

export function stripHtml(value: string | null | undefined): string {
    return String(value ?? '')
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}
