import createDOMPurify from 'dompurify';
import { marked } from 'marked';
import TurndownService from 'turndown';
import type { BlogContentFormat } from '@/types';

type DOMPurifyInstance = ReturnType<typeof createDOMPurify>;

let purifier: DOMPurifyInstance | null = null;
let turndownService: TurndownService | null = null;

marked.setOptions({
    async: false,
    gfm: true,
    breaks: true,
});

function getPurifier(): DOMPurifyInstance | null {
    if (typeof window === 'undefined') {
        return null;
    }

    purifier ??= createDOMPurify(window);

    return purifier;
}

function getTurndownService(): TurndownService {
    turndownService ??= new TurndownService({
        bulletListMarker: '-',
        codeBlockStyle: 'fenced',
        headingStyle: 'atx',
    });

    return turndownService;
}

export function sanitizeClientHtml(html: string): string {
    const activePurifier = getPurifier();

    if (!activePurifier) {
        return html
            .replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '')
            .replace(/\son\w+="[^"]*"/gi, '')
            .replace(/\son\w+='[^']*'/gi, '');
    }

    return activePurifier.sanitize(html, {
        ALLOWED_TAGS: [
            'a',
            'blockquote',
            'br',
            'code',
            'em',
            'h1',
            'h2',
            'h3',
            'h4',
            'hr',
            'li',
            'ol',
            'p',
            'pre',
            'strong',
            'ul',
        ],
        ALLOWED_ATTR: ['href', 'rel', 'target', 'title'],
    });
}

export function renderBlogPreviewHtml(
    format: BlogContentFormat,
    bodySource: string,
): string {
    if (!bodySource.trim()) {
        return '';
    }

    const html = format === 'markdown'
        ? (marked.parse(bodySource) as string)
        : bodySource;

    return sanitizeClientHtml(html);
}

export function convertBlogContentMode(
    bodySource: string,
    from: BlogContentFormat,
    to: BlogContentFormat,
): string {
    if (from === to) {
        return bodySource;
    }

    if (from === 'markdown' && to === 'rich_text') {
        return renderBlogPreviewHtml('markdown', bodySource);
    }

    return getTurndownService().turndown(sanitizeClientHtml(bodySource));
}
