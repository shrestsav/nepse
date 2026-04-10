<script setup lang="ts">
import { Link as LinkExtension } from '@tiptap/extension-link';
import StarterKit from '@tiptap/starter-kit';
import { Editor, EditorContent } from '@tiptap/vue-3';
import {
    Bold,
    Code2,
    Heading2,
    Heading3,
    Italic,
    Link2,
    List,
    ListOrdered,
    Pilcrow,
    Quote,
    Unlink,
} from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, shallowRef, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const props = defineProps<{
    modelValue: string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

const editor = shallowRef<Editor | null>(null);

onMounted(() => {
    editor.value = new Editor({
        content: props.modelValue,
        extensions: [
            StarterKit.configure({
                heading: {
                    levels: [2, 3],
                },
            }),
            LinkExtension.configure({
                openOnClick: false,
                protocols: ['http', 'https', 'mailto'],
            }),
        ],
        editorProps: {
            attributes: {
                class: 'blog-editor-surface min-h-[320px] focus:outline-none',
            },
        },
        onUpdate: ({ editor: tiptapEditor }) => {
            emit('update:modelValue', tiptapEditor.getHTML());
        },
    });
});

watch(
    () => props.modelValue,
    (value) => {
        if (!editor.value || value === editor.value.getHTML()) {
            return;
        }

        editor.value.commands.setContent(value, {
            emitUpdate: false,
        });
    },
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

function editorInstance(): Editor | undefined {
    return editor.value ?? undefined;
}

function setLink(): void {
    if (!editor.value || typeof window === 'undefined') {
        return;
    }

    const previousUrl = editor.value.getAttributes('link').href ?? '';
    const url = window.prompt('Enter link URL', previousUrl);

    if (url === null) {
        return;
    }

    if (!url.trim()) {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();

        return;
    }

    editor.value
        .chain()
        .focus()
        .extendMarkRange('link')
        .setLink({
            href: url.trim(),
            target: '_blank',
            rel: 'noopener noreferrer',
        })
        .run();
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-wrap gap-2">
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="cn(editor?.isActive('paragraph') && 'bg-accent')"
                @click="editor?.chain().focus().setParagraph().run()"
            >
                <Pilcrow class="size-4" />
                Paragraph
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="cn(editor?.isActive('heading', { level: 2 }) && 'bg-accent')"
                @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
            >
                <Heading2 class="size-4" />
                H2
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="cn(editor?.isActive('heading', { level: 3 }) && 'bg-accent')"
                @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
            >
                <Heading3 class="size-4" />
                H3
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="cn(editor?.isActive('bold') && 'bg-accent')"
                @click="editor?.chain().focus().toggleBold().run()"
            >
                <Bold class="size-4" />
                Bold
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="cn(editor?.isActive('italic') && 'bg-accent')"
                @click="editor?.chain().focus().toggleItalic().run()"
            >
                <Italic class="size-4" />
                Italic
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="cn(editor?.isActive('bulletList') && 'bg-accent')"
                @click="editor?.chain().focus().toggleBulletList().run()"
            >
                <List class="size-4" />
                Bullet list
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="cn(editor?.isActive('orderedList') && 'bg-accent')"
                @click="editor?.chain().focus().toggleOrderedList().run()"
            >
                <ListOrdered class="size-4" />
                Ordered list
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="cn(editor?.isActive('blockquote') && 'bg-accent')"
                @click="editor?.chain().focus().toggleBlockquote().run()"
            >
                <Quote class="size-4" />
                Quote
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="cn(editor?.isActive('codeBlock') && 'bg-accent')"
                @click="editor?.chain().focus().toggleCodeBlock().run()"
            >
                <Code2 class="size-4" />
                Code
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :class="cn(editor?.isActive('link') && 'bg-accent')"
                @click="setLink"
            >
                <Link2 class="size-4" />
                Link
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                @click="editor?.chain().focus().unsetLink().run()"
            >
                <Unlink class="size-4" />
                Unlink
            </Button>
        </div>

        <div class="rounded-xl border border-border/70 bg-background px-4 py-3">
            <EditorContent v-if="editor" :editor="editorInstance()" />
            <div
                v-else
                class="min-h-[320px] rounded-lg border border-dashed border-border/70 bg-muted/30"
            />
        </div>
    </div>
</template>
