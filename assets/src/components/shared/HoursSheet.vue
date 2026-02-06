<template>
  <BottomSheet 
    :visible="visible" 
    @update:visible="$emit('update:visible', $event)"
    title="Horário de Atendimento"
    subtitle="Funcionamento Geral da Casa"
  >
    <div class="flex flex-col gap-2 pb-8">
        <div 
            v-for="(day, key) in weekDays" 
            :key="key"
            class="flex justify-between items-center p-3 rounded-xl border transition-colors"
            :class="isToday(key) 
                ? 'bg-primary-500/10 border-primary-500 text-surface-0' 
                : 'bg-surface-2 border-transparent text-surface-400'"
        >
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-black"
                     :class="isToday(key) ? 'bg-primary-500 text-surface-950' : 'bg-surface-3 text-surface-500'">
                    {{ day.short }}
                </div>
                <span class="text-sm font-bold uppercase tracking-wide">{{ day.label }}</span>
            </div>

            <div class="text-xs font-black">
                <span v-if="getHours(key).active">
                    {{ getHours(key).start }} às {{ getHours(key).end }}
                </span>
                <span v-else class="text-red-400 opacity-70 uppercase tracking-wider text-[10px]">
                    Fechado
                </span>
            </div>
        </div>
    </div>
  </BottomSheet>
</template>

<script setup>
import { computed } from 'vue';
import { useSettingsStore } from '@/stores/settings-store';
import BottomSheet from '@/components/shared/BottomSheet.vue';

const props = defineProps(['visible']);
defineEmits(['update:visible']);

const settingsStore = useSettingsStore();

const weekDays = {
    mon: { label: 'Segunda-feira', short: 'SEG' },
    tue: { label: 'Terça-feira', short: 'TER' },
    wed: { label: 'Quarta-feira', short: 'QUA' },
    thu: { label: 'Quinta-feira', short: 'QUI' },
    fri: { label: 'Sexta-feira', short: 'SEX' },
    sat: { label: 'Sábado', short: 'SÁB' },
    sun: { label: 'Domingo', short: 'DOM' }
};

const getHours = (dayKey) => {
    if (settingsStore.hours?.general?.[dayKey]) {
        return settingsStore.hours.general[dayKey];
    }
    return { active: false };
};

const isToday = (dayKey) => {
    const days = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
    const todayIndex = new Date().getDay();
    return days[todayIndex] === dayKey;
};
</script>