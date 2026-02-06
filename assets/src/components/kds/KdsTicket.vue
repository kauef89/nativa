<template>
  <div class="bg-surface-2 rounded-[32px] flex flex-col w-full shadow-lg transition-all duration-300 relative overflow-hidden group hover:shadow-2xl border border-surface-4/10"
       :class="[{'opacity-60 grayscale filter': isDoneColumn, 'ring-2 ring-yellow-500/50 shadow-[0_0_30px_rgba(234,179,8,0.15)] animate-pulse-slow': isFirstTodo}]">
    
    <div class="p-5 flex justify-between items-start gap-3 bg-surface-2 z-20 relative cursor-pointer hover:bg-surface-3/50 transition-colors" 
         @click="toggleCollapse">
        
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-full flex items-center justify-center text-lg shadow-inner shrink-0"
                 :class="modalityColorClass">
                <i :class="modalityIcon"></i>
            </div>
            <div class="flex flex-col">
                <span class="text-sm font-black uppercase tracking-wider text-surface-0 leading-none">
                    {{ modalityLabel }}
                </span>
                
                <div class="flex items-center gap-2 mt-1">
                    <span class="text-[10px] font-bold text-surface-400">
                        #{{ order.id }} 
                        <span v-if="order.server_name"> | {{ formatName(order.server_name) }}</span>
                    </span>
                    <span v-if="order.source === 'web'" class="px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-400 text-[8px] font-black uppercase">WEB</span>
                </div>
            </div>
        </div>

        <div class="flex flex-col items-end min-w-0 flex-1 pl-2">
            <div class="flex items-baseline gap-1 mb-0.5" :class="timeColorClass">
                <span class="text-2xl font-black tabular-nums leading-none">
                    {{ formatTime(order.waitMinutes) }}
                </span>
                <span v-if="order.waitMinutes < 60" class="text-[9px] font-bold uppercase">min</span>
            </div>
            <div class="text-xs font-bold text-surface-200 truncate max-w-[140px] leading-tight text-right">
                {{ formatName(order.client) }}
            </div>
        </div>
    </div>

    <div v-if="isCollapsed" 
         class="bg-surface-3/30 py-3 flex items-center justify-center gap-2 cursor-pointer hover:bg-surface-3 transition-colors text-surface-400 hover:text-surface-200 border-t border-surface-0/5" 
         @click.stop="isCollapsed = false">
        <span class="text-[10px] font-black uppercase tracking-[0.2em]">{{ order.displayItems.length }} ITENS</span>
    </div>

    <div v-else class="flex-1 flex flex-col bg-surface-2 relative z-10 border-t border-surface-0/5">
        
        <div v-if="isPeeking" class="flex-1 flex flex-col animate-fade-in">
            <div class="p-2 text-center bg-yellow-500/10 text-yellow-500 text-[9px] font-bold uppercase tracking-widest border-b border-yellow-500/20">
                Modo Observação
            </div>
            <div class="flex-1 overflow-y-auto p-3 bg-surface-950/20">
                </div>
        </div>

        <div v-else>
            <div v-for="(item, index) in order.displayItems" :key="item.id" 
                class="relative p-4 transition-all duration-300 border-b border-surface-0/5 last:border-0 hover:bg-surface-3"
                :class="{'bg-green-500/5': item.status === 'finished'}"
            >
                <div class="flex flex-row items-center justify-between gap-3 cursor-pointer select-none"
                    @click="openOverlay(item.id)">
                    
                    <div class="flex-1 min-w-0" :class="{'opacity-50 grayscale': item.status === 'finished'}">
                        <div class="flex items-start gap-3">
                            <span class="text-base font-black text-surface-0 min-w-[20px] tabular-nums">{{ item.qty }}</span>
                            <div>
                                <div class="text-sm font-black text-surface-200 leading-tight uppercase flex flex-wrap gap-2 items-center">
                                    {{ item.name }}
                                    <span v-if="hasViagemTag(item)" class="bg-primary-500/10 text-primary-300 border border-primary-500/20 text-[9px] px-2 py-0.5 rounded-full font-black uppercase">
                                        <i class="fa-solid fa-bag-shopping mr-1"></i> Viagem
                                    </span>
                                    </div>

                                <div v-if="getFilteredModifiers(item).length" class="mt-1.5 space-y-0.5">
                                    <div v-for="(mod, i) in getFilteredModifiers(item)" :key="i" 
                                        class="text-xs text-white uppercase font-bold flex items-center gap-1.5 leading-tight tracking-wide">
                                        <span class="text-surface-500 text-[10px]">↳</span> {{ mod.name }}
                                    </div>
                                </div>
                                <div v-if="item.notes" class="mt-2 text-[10px] text-orange-300 font-bold bg-orange-500/10 px-2 py-1 rounded-lg inline-block">
                                    <i class="fa-solid fa-circle-info mr-1"></i> {{ item.notes }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 relative z-10">
                        <button v-if="getTimerState(item.id).running && item.requires_prep" 
                            @click.stop="emit('request-timer', order, item)"
                            class="h-8 pl-1 pr-2 rounded-full bg-red-500/10 text-red-400 border border-red-500/30 flex items-center gap-1 transition-all hover:bg-red-500/20 hover:scale-105 active:scale-95 animate-pulse">
                            <div class="w-6 h-6 rounded-full bg-red-500 text-surface-950 flex items-center justify-center font-black text-[10px]">
                                <i class="fa-solid fa-stopwatch"></i>
                            </div>
                            <span class="text-[10px] font-black tabular-nums">{{ formatTimer(getTimerState(item.id).remaining) }}</span>
                        </button>
                        
                        <button v-if="item.status === 'finished'" 
                                class="w-9 h-9 rounded-full bg-green-500 text-surface-950 border-2 border-green-400 flex items-center justify-center shadow-lg transition-transform hover:scale-110 hover:rotate-180 active:scale-95"
                                @click.stop="toggleItemAndClose(order.id, item.id)"
                                v-tooltip.left="'Desfazer'">
                            <i class="fa-solid fa-check text-sm font-black"></i>
                        </button>
                    </div>
                </div>

                <transition name="fade"> 
                    <div v-if="activeOverlayId === item.id" 
                        class="absolute inset-0 bg-surface-3/95 z-20 flex items-center justify-between px-4 animate-duration-200 rounded-[20px]"
                        @click.stop
                    >
                        <button class="w-10 h-10 rounded-full flex items-center justify-center text-surface-400 hover:text-surface-0 hover:bg-surface-4 transition-colors" 
                                @click="activeOverlayId = null">
                            <i class="fa-solid fa-chevron-left text-lg"></i>
                        </button>

                        <div class="flex items-center gap-3">
                            <button v-if="item.requires_prep"
                                class="w-12 h-12 rounded-full flex items-center justify-center font-bold transition-all border shrink-0 active:scale-90 shadow-lg"
                                :class="getTimerState(item.id).running ? 'bg-red-500 text-surface-950 border-red-400 animate-pulse' : 'bg-surface-4 text-surface-300 border-surface-0/10 hover:bg-surface-5 hover:text-white'"
                                @click.stop="emit('request-timer', order, item)">
                                <i class="fa-solid text-lg" :class="getTimerState(item.id).running ? 'fa-stop' : 'fa-stopwatch'"></i>
                            </button>

                            <button class="w-12 h-12 rounded-full bg-surface-4 text-surface-300 border border-surface-0/10 flex items-center justify-center shrink-0 active:scale-90 hover:bg-surface-5 hover:text-white shadow-lg transition-all"
                                @click.stop="$emit('print-item', order, item)">
                                <i class="fa-solid fa-print text-lg"></i>
                            </button>

                            <button class="w-12 h-12 rounded-full flex items-center justify-center transition-all border shrink-0 active:scale-95 shadow-xl"
                                :class="item.status === 'finished' ? 'bg-surface-1 text-green-500 border-green-500/30' : 'bg-green-500 text-surface-950 border-green-400 hover:bg-green-400'"
                                @click.stop="toggleItemAndClose(order.id, item.id)">
                                <i class="fa-solid text-xl" :class="item.status === 'finished' ? 'fa-rotate-left' : 'fa-check'"></i>
                            </button>
                        </div>
                    </div>
                </transition>
            </div>
        </div>
    </div>

    <div class="p-4 bg-surface-2/50 border-t border-surface-0/5 flex gap-2 items-center" v-if="!isCollapsed && !isDoneColumn">
        <button class="w-12 h-12 rounded-full bg-surface-3 hover:bg-surface-4 text-surface-400 hover:text-surface-0 flex items-center justify-center transition-colors shrink-0 border border-surface-0/5" @click.stop="isCollapsed = true"><i class="fa-solid fa-chevron-up"></i></button>
        <Button icon="fa-solid fa-print" class="!w-12 !h-12 !rounded-full !bg-surface-3 hover:!bg-surface-4 !border-surface-0/5 hover:!border-surface-0/20 !text-surface-400 hover:!text-surface-0 shrink-0" @click.stop="$emit('print-order', order)" />
        <Button icon="fa-solid fa-eye" class="!w-12 !h-12 !rounded-full !bg-surface-3 !border-surface-0/5 hover:!border-surface-0/20 !text-surface-400 shrink-0" :class="isPeeking ? '!bg-red-500 !text-white !border-red-600 hover:!bg-red-400' : 'hover:!bg-surface-4 hover:!text-surface-0'" @click.stop="togglePeek" />
        
        <button v-if="column === 'todo'" class="flex-1 h-12 rounded-full font-black uppercase tracking-widest shadow-lg transition-all active:scale-[0.98] flex items-center justify-center gap-2 text-xs bg-blue-500 hover:bg-blue-400 text-surface-950" @click.stop="$emit('advance', order)">
            <i :class="mainActionIcon"></i> {{ mainActionLabel }}
        </button>
    </div>
    
    <div v-else-if="!isCollapsed && isDoneColumn" class="p-4 border-t border-surface-0/5 bg-surface-2/50">
         <button class="w-full h-12 rounded-full bg-surface-3 hover:bg-green-600 hover:text-white text-green-400 border border-green-500/20 font-black uppercase tracking-widest transition-all active:scale-[0.98] flex items-center justify-center gap-2 text-xs" @click.stop="$emit('finish', order)"><i class="fa-solid fa-check-double"></i> Entregue</button>
    </div>

  </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { useFormat } from '@/composables/useFormat';
import { useKdsStore } from '@/stores/kds-store';
import Button from 'primevue/button';

const props = defineProps(['order', 'column', 'isFirst']);
const emit = defineEmits(['advance', 'finish', 'print-order', 'toggle-item', 'print-item', 'request-timer']);
const { abbreviateName } = useFormat();
const kdsStore = useKdsStore();

// Estados Locais (Apenas UI)
const isCollapsed = ref(false);
const activeOverlayId = ref(null);
const isPeeking = ref(false);
const activePeekTab = ref('');

const isDoneColumn = computed(() => props.column === 'done');
const isFirstTodo = computed(() => props.column === 'todo' && props.isFirst);

// --- LÓGICA DE GRAB & GO (Visual) ---
const isAllGrabAndGo = computed(() => {
    if (!props.order.displayItems || props.order.displayItems.length === 0) return false;
    return props.order.displayItems.every(i => !i.requires_prep);
});

const mainActionLabel = computed(() => isAllGrabAndGo.value ? 'LEVAR' : 'FAZER');
const mainActionIcon = computed(() => isAllGrabAndGo.value ? 'fa-solid fa-person-walking' : 'fa-solid fa-fire-burner');

onMounted(() => {
    if (props.column === 'todo' && !props.isFirst) isCollapsed.value = true;
});

const toggleCollapse = () => { isCollapsed.value = !isCollapsed.value; };
const openOverlay = (itemId) => { if (isDoneColumn.value) return; activeOverlayId.value = activeOverlayId.value === itemId ? null : itemId; };
const toggleItemAndClose = (orderId, itemId) => { kdsStore.toggleItemStatus(orderId, itemId); activeOverlayId.value = null; };

const formatTime = (mins) => { if (isNaN(mins)) return '0'; if (mins >= 60) { const h = Math.floor(mins / 60); const m = mins % 60; return `${h}h ${m.toString().padStart(2, '0')}m`; } return mins; };
const formatName = (name) => abbreviateName(name);

const timeColorClass = computed(() => { const m = props.order.waitMinutes || 0; if (m > 30) return 'text-red-500 animate-pulse'; if (m > 15) return 'text-orange-400'; return 'text-green-400'; });
const modalityLabel = computed(() => { if (props.order.modality === 'delivery') return 'Delivery'; if (props.order.modality === 'pickup') return 'Retirada'; return props.order.client_name ? props.order.client_name : 'Mesa'; });
const modalityIcon = computed(() => { if (props.order.modality === 'delivery') return 'fa-solid fa-motorcycle'; if (props.order.modality === 'pickup') return 'fa-solid fa-bag-shopping'; return 'fa-solid fa-utensils'; });
const modalityColorClass = computed(() => { if (props.order.modality === 'delivery') return 'bg-orange-500/10 text-orange-500 border border-orange-500/20'; if (props.order.modality === 'pickup') return 'bg-blue-500/10 text-blue-500 border border-blue-500/20'; return 'bg-purple-500/10 text-purple-500 border border-purple-500/20'; });

const hasViagemTag = (item) => item.modifiers && item.modifiers.some(m => m.name.includes('[VIAGEM]'));
const getFilteredModifiers = (item) => item.modifiers ? item.modifiers.filter(m => !m.name.includes('[VIAGEM]')) : [];

const otherStationsData = computed(() => {
    const currentStation = kdsStore.selectedCozinhaId;
    const groups = {};
    (props.order.items || []).forEach(item => { const itemStation = item.station_id || 'default'; if (itemStation != currentStation) { if (!groups[itemStation]) groups[itemStation] = []; groups[itemStation].push(item); } });
    return groups;
});

const togglePeek = () => { isPeeking.value = !isPeeking.value; if (isPeeking.value) { const keys = Object.keys(otherStationsData.value); if (keys.length > 0) activePeekTab.value = keys[0]; } };
const activePeekItems = computed(() => otherStationsData.value[activePeekTab.value] || []);

// Helpers de Timer (Leitura da Store)
const getTimerState = (itemId) => { const t = kdsStore.timers[itemId]; return t ? { ...t, running: true } : { running: false, remaining: 0 }; };
const formatTimer = (sec) => { if (!sec && sec !== 0) return '00:00'; const m = Math.floor(sec / 60); const s = sec % 60; return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`; };
</script>

<style scoped>
.animate-pulse-slow { animation: pulse 2.5s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
@keyframes pulse { 0%, 100% { border-color: rgba(234,179,8,0.6); box-shadow: 0 0 25px rgba(234,179,8,0.2); } 50% { border-color: rgba(234,179,8,0.2); box-shadow: none; } }
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>