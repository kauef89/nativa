<template>
  <Drawer 
    v-bind="$attrs"
    :visible="visible"
    @update:visible="$emit('update:visible', $event)"
    position="bottom"
    class="!h-auto !max-h-[90vh] !rounded-t-[32px] !border-none"
    :pt="{
        root: { class: '!bg-surface-1 !border-t !border-surface-3/10 !flex !flex-col !shadow-[0_-10px_40px_rgba(0,0,0,0.6)]' },
        header: { class: '!p-0 !hidden' },
        content: { class: '!p-0 !overflow-hidden !flex !flex-col' },
        mask: { class: '!bg-surface-base/80 backdrop-blur-sm' }
    }"
  >
    <div class="w-full flex justify-center pt-3 pb-1 shrink-0 cursor-grab active:cursor-grabbing" @click="$emit('update:visible', false)">
        <div class="w-12 h-1.5 bg-surface-3/50 rounded-full"></div>
    </div>

    <div v-if="title || subtitle || $slots.header" class="px-6 py-4 border-b border-surface-3/10 shrink-0 flex items-center justify-between">
        <div class="flex flex-col">
            <h3 v-if="title" class="text-lg font-black text-surface-0 leading-tight">{{ title }}</h3>
            <p v-if="subtitle" class="text-xs text-surface-400 font-bold mt-0.5">{{ subtitle }}</p>
        </div>
        <div class="flex gap-2">
            <slot name="actions"></slot>
        </div>
    </div>

    <div v-if="flush" class="flex flex-col w-full">
        <slot></slot>
    </div>
    
    <div v-else class="flex-1 overflow-y-auto p-6 scrollbar-thin">
        <slot></slot>
    </div>

    <div v-if="$slots.footer" class="p-4 border-t border-surface-3/10 bg-surface-1 shrink-0 pb-8 md:pb-4">
        <slot name="footer"></slot>
    </div>
  </Drawer>
</template>

<script setup>
import Drawer from 'primevue/drawer';

defineProps({
    visible: Boolean,
    title: String,
    subtitle: String,
    flush: { type: Boolean, default: false } // Nova prop para remover padding/scroll padrão
});

defineEmits(['update:visible']);
</script>