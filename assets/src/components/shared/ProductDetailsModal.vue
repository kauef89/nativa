<template>
  <component 
    :is="componentType"
    v-model:visible="isOpen" 
    :modal="true" 
    :position="isMobile ? 'bottom' : 'center'"
    :dismissableMask="true"
    :closable="false"
    :style="modalStyle"
    class="bg-surface-1 transition-all duration-300"
    :pt="ptOptions"
    @hide="onHide"
  >
    <div v-if="product" class="flex flex-col h-full w-full overflow-hidden relative">
      
        <div 
            class="relative w-full overflow-hidden shrink-0 group transition-all duration-500 ease-in-out bg-surface-1 border-b border-surface-3/10 z-20"
            :class="headerClass"
        >
            <div class="absolute inset-0 z-0 transition-opacity duration-500" :class="isCollapsed ? 'opacity-0' : 'opacity-100'">
                <img v-if="product?.image" :src="product.image" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full bg-surface-2 flex items-center justify-center text-surface-600">
                    <i class="fa fa-regular fa-image text-4xl"></i>
                </div>
                <div class="absolute inset-0 z-10 bg-gradient-to-t from-surface-1 via-surface-1/50 to-transparent"></div>
            </div>

            <div class="absolute inset-0 z-10 bg-surface-1 transition-opacity duration-500" :class="isCollapsed ? 'opacity-100' : 'opacity-0'"></div>

            <div class="absolute inset-0 z-20 overflow-hidden pointer-events-none">
                <div class="absolute top-4 right-4 z-50 pointer-events-auto">
                    <button 
                        class="w-8 h-8 rounded-full flex items-center justify-center transition-all duration-300 active:scale-95 backdrop-blur-md border border-surface-0/10 shadow-lg"
                        :class="isCollapsed ? 'bg-surface-3 text-surface-200 hover:bg-surface-2' : 'bg-black/40 text-white hover:bg-black/60'"
                        @click="isOpen = false"
                    >
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>

                <div class="absolute transition-all duration-500 ease-in-out w-full px-6" :class="titlePositionClass">
                    <h2 class="font-black text-surface-0 leading-tight transition-all duration-500 whitespace-nowrap overflow-hidden text-ellipsis drop-shadow-lg"
                        :class="isCollapsed ? 'text-lg max-w-[70%]' : 'text-3xl'"
                    >
                        {{ product?.name }}
                    </h2>
                </div>

                <div class="absolute bottom-4 left-0 right-14 px-6 text-left transition-all duration-500"
                     :class="isCollapsed ? 'opacity-0 translate-y-10' : 'opacity-100 translate-y-0 delay-100'"
                >
                    <p v-if="product?.description" class="text-surface-300 text-xs leading-snug line-clamp-2 font-medium drop-shadow-md">
                        {{ product.description }}
                    </p>
                </div>
                
                <div class="absolute bottom-4 right-4 z-50 pointer-events-auto">
                    <button 
                        class="w-8 h-8 rounded-full flex items-center justify-center transition-all duration-300 active:scale-95 backdrop-blur-md border border-white/20 shadow-lg"
                        :class="isCollapsed ? 'bg-surface-3 text-surface-0 hover:bg-surface-2' : 'bg-black/40 text-white hover:bg-black/60'"
                        @click="toggleHeader"
                        v-tooltip.left="isCollapsed ? 'Expandir' : 'Recolher'"
                    >
                        <i class="fa-solid text-xs" :class="isCollapsed ? 'fa-chevron-down' : 'fa-chevron-up'"></i>
                    </button>
                </div>
            </div>

            <div v-if="isMobile" 
                 class="absolute top-3 left-1/2 -translate-x-1/2 w-12 h-1.5 rounded-full z-30 backdrop-blur-sm transition-all duration-500"
                 :class="isCollapsed ? 'bg-surface-3 opacity-0' : 'bg-white/30 opacity-100'"
            ></div>
        </div>

        <div class="flex-1 overflow-y-auto space-y-0 pb-32 bg-surface-1" ref="scrollContainer">
            <div v-for="group in productGroups" :key="group.id" class="bg-surface-1 overflow-hidden border-b border-surface-3/10 last:border-0 relative">
                
                <div class="sticky top-0 z-30 bg-surface-1/95 backdrop-blur-md px-6 py-4 flex flex-col gap-2 border-b border-surface-3/10 shadow-sm transition-all">
                    <div class="flex justify-between items-center">
                        <div class="font-extrabold text-sm text-surface-100 uppercase tracking-tight">{{ group.title }}</div>
                        
                        <div class="flex items-center gap-2">
                            <span v-if="group.min > 0" class="text-[9px] font-black uppercase tracking-widest bg-surface-2 px-2 py-0.5 rounded text-surface-400 border border-surface-3/20" :class="{'!text-primary-400 !border-primary-500/20': validateGroup(group)}">
                                {{ getGroupStatus(group) }}
                            </span>
                            <Button v-if="hasSelection(group)" icon="fa fa-solid fa-eraser" text rounded class="!w-6 !h-6 !text-surface-500 hover:!text-surface-0" @click="clearGroup(group)" />
                        </div>
                    </div>
                    
                    <div v-if="isIngredientGroup(group) && (group.free_limit > 0 || group.min > 0)" class="w-full h-1 bg-surface-2 rounded-full overflow-hidden relative">
                        <div :style="{ width: calculateTotalWidth(group) + '%' }" :class="getProgressColor(group)" class="absolute left-0 h-full transition-all duration-500 ease-in-out rounded-full"></div>
                    </div>
                </div>

                <div v-if="isIngredientGroup(group)" class="flex flex-wrap gap-2 px-6 py-4">
                    <div v-for="item in getSortedItems(group)" :key="item.name" 
                        class="cursor-pointer rounded-lg px-4 py-3 transition-all duration-200 flex items-center select-none text-xs font-bold border"
                        :class="isSelected(group, item) ? 'bg-white border-surface-0 text-surface-950 shadow-sm' : 'bg-surface-2 border-surface-3/20 text-surface-300 hover:border-surface-400'"
                        @click="toggleSelection(group, item)"
                    >
                        <i v-if="isSelected(group, item)" class="fa fa-solid fa-check mr-2 text-[10px]"></i>
                        <span>{{ item.name }}</span>
                        <span v-if="calculateItemVisualPrice(group, item) > 0" class="ml-1.5 text-[9px] font-black opacity-60">
                            + {{ formatCurrency(calculateItemVisualPrice(group, item)) }}
                        </span>
                    </div>
                </div>

                <div v-else class="flex flex-col"> 
                    <div v-for="(item, idx) in getSortedItems(group)" :key="idx"
                        class="flex items-center justify-between px-6 py-4 transition-colors cursor-pointer hover:bg-surface-2 group/item border-b border-surface-3/5 last:border-0"
                        :class="isSelected(group, item) ? 'bg-surface-2/30' : ''"
                        @click="handleItemClick(group, item)"
                    >
                        <div class="flex items-center min-w-0 flex-1 pr-4">
                            <div v-if="!group.enable_qty" class="mr-4 flex items-center scale-110 shrink-0">
                                <Checkbox v-if="group.type === 'adicional'" :modelValue="isSelected(group, item)" :binary="true" readonly class="pointer-events-none" />
                                <RadioButton v-else :modelValue="isSelected(group, item)" :value="true" readonly class="pointer-events-none" />
                            </div>

                            <div class="flex flex-col min-w-0">
                                <label class="cursor-pointer font-bold text-surface-200 text-sm leading-tight truncate group-hover/item:text-surface-0 transition-colors" :class="{'!text-primary-400': isSelected(group, item)}">
                                    {{ item.name }}
                                </label>
                                <span v-if="item.price > 0" class="text-surface-500 font-bold text-[10px] mt-1">
                                    + {{ formatCurrency(item.price) }}
                                </span>
                            </div>
                        </div>

                        <div v-if="group.enable_qty" class="shrink-0" @click.stop>
                            <div v-if="isSelected(group, item)" class="flex items-center bg-surface-950 rounded-full h-8 border border-surface-3/30 shadow-sm px-0.5 animate-scale-in">
                                <button class="w-8 h-full flex items-center justify-center text-surface-400 hover:text-white transition-colors rounded-l-full active:bg-surface-800" @click.stop="decrementItemQty(group, item)">
                                    <i class="fa-solid fa-minus text-[9px]"></i>
                                </button>
                                <span class="w-6 text-center font-black text-surface-0 text-xs tabular-nums">{{ getItemQty(group, item) }}</span>
                                <button class="w-8 h-full flex items-center justify-center text-primary-400 hover:text-white transition-colors rounded-r-full active:bg-surface-800" @click.stop="incrementItemQty(group, item)">
                                    <i class="fa-solid fa-plus text-[9px]"></i>
                                </button>
                            </div>
                            <button v-else 
                                class="h-8 w-8 rounded-full bg-surface-2 border border-surface-3/30 flex items-center justify-center text-surface-400 hover:text-primary-400 hover:border-primary-500/30 transition-all active:scale-90"
                                @click.stop="incrementItemQty(group, item)"
                            >
                                <i class="fa-solid fa-plus text-xs"></i>
                            </button>
                        </div>
                        
                        <div v-else-if="isSelected(group, item)" class="text-primary-500 text-sm animate-scale-in">
                            <i class="fa-solid fa-check"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <template #footer>
        <div class="flex flex-col w-full pt-2 gap-3 bg-surface-1 border-t border-surface-3/10 px-6 py-6 shadow-[0_-5px_20px_rgba(0,0,0,0.3)] z-30">
            
            <div v-if="shouldShowTakeoutToggle" class="flex items-center justify-between bg-surface-2 p-2.5 rounded-[16px] border border-surface-3/10">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-surface-1 flex items-center justify-center text-primary-500 shadow-inner border border-surface-3/10">
                        <i class="fa-solid fa-bag-shopping text-xs"></i>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs font-black text-surface-0 uppercase tracking-tighter">Para Viagem?</span>
                        <span class="text-[9px] text-surface-500 font-bold uppercase">Embalar separadamente</span>
                    </div>
                </div>
                <ToggleSwitch v-model="isTakeout" :pt="{ root: { class: '!w-10 !h-6' }, slider: { class: '!bg-surface-400' } }" />
            </div>
            
            <div v-else-if="isForcedTakeout" class="flex justify-center pb-1">
                <span class="text-[9px] font-black uppercase text-surface-500 bg-surface-2 px-3 py-1 rounded-full border border-surface-3/20 flex items-center gap-2">
                    <i class="fa-solid fa-motorcycle"></i> Item para Viagem / Entrega
                </span>
            </div>

            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center bg-surface-2 rounded-full h-12 shrink-0 border border-surface-3/20 shadow-lg px-1 w-32 justify-between">
                    <button class="w-10 h-10 flex items-center justify-center text-surface-400 hover:text-surface-0 transition-colors rounded-full active:bg-surface-3" @click="decrementQty">
                        <i class="fa fa-solid fa-minus text-xs"></i>
                    </button>
                    <span class="font-black text-surface-0 text-lg tabular-nums">{{ quantity }}</span>
                    <button class="w-10 h-10 flex items-center justify-center text-surface-400 hover:text-surface-0 transition-colors rounded-full active:bg-surface-3" @click="incrementQty">
                        <i class="fa fa-solid fa-plus text-xs"></i>
                    </button>
                </div>
                
                <button 
                    class="h-12 flex-1 bg-surface-0 hover:bg-primary-500 text-surface-950 rounded-full px-6 transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed font-black text-sm uppercase tracking-tight shadow-xl flex items-center justify-between group"
                    :class="!isValid ? '!bg-surface-3 !text-surface-500' : ''"
                    :disabled="!isValid"
                    @click="confirmAdd"
                >
                    <span class="truncate mr-2">{{ editingIndex !== null ? 'Salvar' : 'Adicionar' }}</span>
                    <span class="bg-surface-950/10 px-2 py-0.5 rounded text-xs tabular-nums group-hover:bg-surface-950/20 transition-colors">
                        {{ formatCurrency(currentTotal * quantity) }}
                    </span>
                </button>
            </div>
        </div>
    </template>
  </component>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useCartStore } from '@/stores/cart-store';
import { useSessionStore } from '@/stores/session-store'; 
import { useFormat } from '@/composables/useFormat';
import { useMobile } from '@/composables/useMobile';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Drawer from 'primevue/drawer';
import Checkbox from 'primevue/checkbox';
import RadioButton from 'primevue/radiobutton';
import ToggleSwitch from 'primevue/toggleswitch';

const props = defineProps({
    visible: Boolean,
    product: Object,
    displayMode: { type: String, default: 'center' },
    showTakeout: { type: Boolean, default: false }
});

const emit = defineEmits(['update:visible', 'saved']);
const store = useCartStore();
const sessionStore = useSessionStore();
const { isMobile } = useMobile();
const cartStore = useCartStore();
const { formatCurrency } = useFormat();

const isOpen = ref(false);
const product = ref(null);
const quantity = ref(1); 
const selections = ref({}); 
const onConfirmCallback = ref(null);
const editingIndex = ref(null);
const isTakeout = ref(false);
const scrollContainer = ref(null);
const isCollapsed = ref(false);

// Controle Manual do Header
const toggleHeader = () => { isCollapsed.value = !isCollapsed.value; };

const componentType = computed(() => isMobile.value ? Drawer : Dialog);
const modalStyle = computed(() => isMobile.value ? { width: '100%', height: 'auto', maxHeight: '95vh' } : { width: '600px', maxHeight: '90vh' });
const isForcedTakeout = computed(() => ['delivery', 'pickup', 'counter'].includes(sessionStore.sessionType));
const shouldShowTakeoutToggle = computed(() => sessionStore.sessionType === 'table' && props.showTakeout);

// --- CSS ROLAGEM CORRIGIDO ---
const ptOptions = computed(() => {
    const common = { 
        header: { class: '!hidden' }, 
        content: { class: '!p-0 !bg-surface-1 !flex !flex-col !h-full !overflow-hidden !rounded-t-[32px] md:!rounded-[28px]' }, 
        footer: { class: '!p-0 !border-none !bg-surface-1' } 
    };
    return isMobile.value ? { ...common, root: { class: '!bg-surface-1 !rounded-t-[32px] !border-none !overflow-hidden' }, mask: { class: '!bg-surface-base/80 backdrop-blur-md' } } : { ...common, root: { class: '!bg-surface-1 !rounded-[28px] !border border-surface-3/20 !shadow-2xl !overflow-hidden' }, mask: { class: '!bg-surface-base/60 backdrop-blur-sm' } };
});

const headerClass = computed(() => isCollapsed.value ? 'h-16' : 'h-64');
const titlePositionClass = computed(() => isCollapsed.value ? 'top-1/2 -translate-y-1/2 left-0 text-left' : 'bottom-12 left-0 text-center');

// --- Lógica de Negócio ---
const isIngredientGroup = (g) => { if (g.enable_qty) return false; return g.type === 'sabor' || g.type === 'ingrediente'; };
const calculateTotalWidth = (group) => { const sel = selections.value[group.id] || []; const currentQty = Array.isArray(sel) ? sel.length : (sel ? 1 : 0); const maxBar = Math.max(group.max || 0, group.free_limit || 0, group.min || 0, currentQty, 1); return (currentQty / maxBar) * 100; };
const getProgressColor = (group) => { const sel = selections.value[group.id] || []; const currentQty = Array.isArray(sel) ? sel.length : (sel ? 1 : 0); return (currentQty > 0 && currentQty < group.min) ? 'bg-red-500' : 'bg-primary-500'; };
const parsePrice = (val) => { if (val === null || val === undefined) return 0; if (typeof val === 'number') return val; const clean = val.toString().replace(',', '.'); return parseFloat(clean) || 0; };

const getSortedItems = (group) => {
    if (!group.items) return [];
    return [...group.items].sort((a, b) => { const aSel = isSelected(group, a); const bSel = isSelected(group, b); if (aSel && !bSel) return -1; if (!aSel && bSel) return 1; const priceA = parsePrice(a.price); const priceB = parsePrice(b.price); if (priceA !== priceB) return priceA - priceB; return a.name.localeCompare(b.name); });
};

// Filtro de Grupos por Canal
const productGroups = computed(() => {
    const rawGroups = product.value?.originalModifiers || product.value?.modifiers || [];
    return rawGroups.filter(group => {
        if (['delivery', 'pickup'].includes(sessionStore.sessionType)) { if (group.show_delivery === false) return false; }
        if (['table', 'counter'].includes(sessionStore.sessionType)) { if (group.show_table === false) return false; }
        return true;
    });
});

const getGroupStatus = (g) => { const sel = selections.value[g.id]; const count = Array.isArray(sel) ? sel.length : (sel ? 1 : 0); if (count < g.min) return `Mínimo: ${g.min}`; if (g.max && count >= g.max) return 'Limite atingido'; return 'Tudo certo'; };

const open = (p, callback = null, editIdx = null, existingSelections = null) => {
  const pCopy = JSON.parse(JSON.stringify(p));
  if (pCopy.image) { pCopy.image = pCopy.image.replace(/-\d+x\d+(\.[a-z]+)$/i, '$1'); }
  product.value = pCopy;
  onConfirmCallback.value = callback;
  editingIndex.value = editIdx;
  quantity.value = (p.qty && editIdx !== null) ? p.qty : 1;
  if (isForcedTakeout.value) { isTakeout.value = true; } else { isTakeout.value = p.is_takeout || false; }
  isCollapsed.value = false;
  if (scrollContainer.value) scrollContainer.value.scrollTop = 0;
  selections.value = existingSelections ? JSON.parse(JSON.stringify(existingSelections)) : {};
  if (!existingSelections && productGroups.value) { productGroups.value.forEach(g => { selections.value[g.id] = (g.type === 'opcao') ? null : []; }); }
  isOpen.value = true;
};

const onHide = () => { isOpen.value = false; };
const incrementQty = () => quantity.value++;
const decrementQty = () => { if(quantity.value > 1) quantity.value--; };
const clearGroup = (group) => selections.value[group.id] = Array.isArray(selections.value[group.id]) ? [] : null;
const hasSelection = (group) => { const s = selections.value[group.id]; return Array.isArray(s) ? s.length > 0 : !!s; };

// --- LÓGICA DE QUANTIDADE HABILITADA ---
const getItemQty = (group, item) => { const list = selections.value[group.id] || []; if (!Array.isArray(list)) return 0; return list.filter(i => i.name === item.name).length; };
const isSelected = (group, item) => { const s = selections.value[group.id]; return Array.isArray(s) ? s.some(i => i.name === item.name) : s?.name === item.name; };
const handleItemClick = (group, item) => { if (group.type === 'adicional') { if (group.enable_qty) { incrementItemQty(group, item); } else { toggleSelection(group, item); } } else { selections.value[group.id] = item; } };
const toggleSelection = (group, item) => { const list = selections.value[group.id]; const idx = list.findIndex(i => i.name === item.name); if (idx > -1) { list.splice(idx, 1); } else if (!group.max || list.length < group.max) { list.push(item); } };
const incrementItemQty = (group, item) => { const list = selections.value[group.id]; if (!group.max || list.length < group.max) { list.push(item); } };
const decrementItemQty = (group, item) => { const list = selections.value[group.id]; const idx = list.findIndex(i => i.name === item.name); if (idx > -1) { list.splice(idx, 1); } };

const calculateItemVisualPrice = (group, item) => { let cost = parsePrice(item.price); if (group.enable_qty) return cost; if (!isIngredientGroup(group)) return cost; const currentList = selections.value[group.id] || []; const selectedIndex = currentList.findIndex(i => i.name === item.name); const pos = selectedIndex > -1 ? selectedIndex : currentList.length; if (group.free_limit > 0 && pos >= group.free_limit) cost += parsePrice(group.increment); return cost; };
const currentTotal = computed(() => { let total = parsePrice(product.value?.price); for (const key in selections.value) { const val = selections.value[key]; const group = productGroups.value.find(g => g.id == key); if (!group || !val) continue; if (Array.isArray(val)) { val.forEach((item, idx) => { let p = parsePrice(item.price); if (isIngredientGroup(group) && !group.enable_qty && idx >= group.free_limit) { p += parsePrice(group.increment); } total += p; }); } else { total += parsePrice(val.price); } } return total; });
const validateGroup = (g) => { const sel = selections.value[g.id]; return Array.isArray(sel) ? sel.length >= g.min : (g.min === 0 || !!sel); };
const isValid = computed(() => productGroups.value.every(g => validateGroup(g)));

const confirmAdd = () => { let flatModifiers = []; for (const key in selections.value) { const val = selections.value[key]; const group = productGroups.value.find(g => g.id == key); if (!val || !group) continue; if (Array.isArray(val)) { val.forEach((item, idx) => { let p = parsePrice(item.price); if (isIngredientGroup(group) && !group.enable_qty && idx >= group.free_limit) p += parsePrice(group.increment); flatModifiers.push({ ...item, price: p, group: group.title }); }); } else { flatModifiers.push({ ...val, group: group.title }); } } if (isTakeout.value) { flatModifiers.push({ name: '[VIAGEM] Embalar p/ levar', price: 0, group: 'Observações' }); } const finalItem = { ...product.value, price: currentTotal.value, qty: quantity.value, modifiers: flatModifiers, originalModifiers: productGroups.value, savedSelections: JSON.parse(JSON.stringify(selections.value)), is_takeout: isTakeout.value }; if (onConfirmCallback.value) onConfirmCallback.value(finalItem); else editingIndex.value !== null ? cartStore.updateItem(editingIndex.value, finalItem) : cartStore.addItem(finalItem); isOpen.value = false; };

defineExpose({ open });
</script>

<style scoped>
.animate-scale-in { animation: scaleIn 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@keyframes scaleIn { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
</style>