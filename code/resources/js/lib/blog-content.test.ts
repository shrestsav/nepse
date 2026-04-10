import { describe, expect, it } from 'vitest';
import { convertBlogContentMode, renderBlogPreviewHtml } from '@/lib/blog-content';

describe('blog content helpers', () => {
    it('renders markdown previews and strips unsafe html', () => {
        const html = renderBlogPreviewHtml(
            'markdown',
            '## Market snapshot\n\n<script>alert("xss")</script>\n\nNEPSE moved higher.',
        );

        expect(html).toContain('<h2>Market snapshot</h2>');
        expect(html).toContain('NEPSE moved higher.');
        expect(html).not.toContain('<script');
    });

    it('converts markdown to rich text html when switching editor mode', () => {
        const html = convertBlogContentMode(
            '## Momentum\n\n- Banks\n- Hydro',
            'markdown',
            'rich_text',
        );

        expect(html).toContain('<h2>Momentum</h2>');
        expect(html).toContain('<li>Banks</li>');
    });

    it('converts rich text back to markdown when switching editor mode', () => {
        const markdown = convertBlogContentMode(
            '<h2>Momentum</h2><p>NEPSE stayed firm.</p>',
            'rich_text',
            'markdown',
        );

        expect(markdown).toContain('## Momentum');
        expect(markdown).toContain('NEPSE stayed firm.');
    });
});
