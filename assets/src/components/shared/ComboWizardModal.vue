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
  >
    <div class="flex flex-col h-full w-full overflow-hidden relative bg-surface-1">
        <div class="shrink-0 z-20 relative bg-surface-1 pb-2">
            <div v-if="isMobile" class="w-full flex justify-center pt-3 pb-1 cursor-grab active:cursor-grabbing" @click="isOpen = false">
                <div class="w-12 h-1.5 bg-surface-3/50 rounded-full"></div>
            </div>
            <div class="flex items-start justify-between px-6 pt-5">
                <div class="flex flex-col gap-1 min-w-0 pr-4">
                    <span class="text-xs font-bold text-primary-500 uppercase tracking-widest flex items-center gap-2">
                        <i class="fa-solid fa-layer-group"></i> Monte seu Combo
                    </span>
                    <h2 class="text-2xl font-black text-surface-0 leading-none truncate">{{ combo?.name }}</h2>
                    <p class="text-surface-400 font-medium text-sm mt-1">
                        A partir de <span class="text-surface-200 font-bold">{{ formatCurrency(combo?.price) }}</span>
                    </p>
                </div>
                <button class="w-10 h-10 rounded-full flex items-center justify-center transition-all bg-surface-2 text-surface-400 hover:text-surface-0 hover:bg-surface-3 shrink-0" @click="isOpen = false">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            <div class="mt-6 px-6 overflow-x-auto scrollbar-hide">
                <div class="flex items-center gap-2 min-w-max">
                    <div v-for="(s, idx) in steps" :key="idx" class="h-1 flex-1 rounded-full transition-all duration-500 min-w-[40px]" :class="idx <= currentStep ? 'bg-primary-500 shadow-[0_0_10px_rgba(var(--primary-500-rgb),0.4)]' : 'bg-surface-3'"></div>
                </div>
                <div class="flex justify-between mt-2 text-[10px] font-black uppercase text-surface-500 tracking-wider">
                    <span>Etapa {{ currentStep + 1 }}</span>
                    <span>{{ steps.length }} Etapas</span>
                </div>
            </div>
        </div>

        <div v-if="isLoading" class="flex-1 flex items-center justify-center bg-surface-1">
            <LoadingSpinner size="large" />
        </div>

        <div v-else class="flex-1 overflow-y-auto p-6 bg-surface-1 scrollbar-thin relative">
            <div v-if="steps[currentStep]" class="animate-fade-in pb-20">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex flex-col">
                        <span class="text-2xl font-black text-surface-0 leading-tight">{{ steps[currentStep].title }}</span>
                        <span class="text-xs font-bold text-surface-400 mt-1">Selecione {{ steps[currentStep].max_qty }} opções</span>
                    </div>
                    <div class="px-3 py-1 bg-surface-2 rounded-full border border-surface-3/10 shadow-sm" :class="{'!border-primary-500/50 !bg-primary-500/10': isStepValid(currentStep)}">
                        <span class="text-xs font-black" :class="isStepValid(currentStep) ? 'text-primary-400' : 'text-surface-400'">{{ selections[currentStep]?.length || 0 }}</span>
                        <span class="text-[10px] text-surface-500 font-bold uppercase mx-1">de</span>
                        <span class="text-xs font-black text-surface-200">{{ steps[currentStep].max_qty }}</span>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div v-for="opt in steps[currentStep].options" :key="opt.id">
                        <button class="group relative w-full p-0 rounded-[20px] bg-surface-2 hover:bg-surface-3 transition-all duration-300 flex flex-col border-2 cursor-pointer overflow-hidden text-left shadow-sm hover:shadow-md active:scale-[0.98]"
                            :class="isSelected(currentStep, opt) ? 'border-primary-500 ring-2 ring-primary-500/20' : 'border-transparent hover:border-surface-3'"
                            @click="handleOptionClick(currentStep, opt)"
                        >
                            <div class="relative w-full h-28 shrink-0 overflow-hidden">
                                <img v-if="opt.image" :src="opt.image" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
                                <div v-else class="flex items-center justify-center h-full w-full bg-surface-1 text-surface-500"><i class="fa-solid fa-utensils text-2xl opacity-20"></i></div>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/95 via-black/40 to-transparent z-10"></div>
                                <div class="absolute bottom-3 left-4 right-10 z-20 flex flex-col">
                                    <span class="text-sm font-black text-white leading-tight line-clamp-1 shadow-black/50 drop-shadow-md">{{ opt.name }}</span>
                                    <span v-if="opt.price > 0" class="text-[10px] font-bold text-primary-400 mt-0.5 shadow-black/50 drop-shadow-md">+ {{ formatCurrency(opt.price) }}</span>
                                </div>
                                <transition name="scale">
                                    <div v-if="isSelected(currentStep, opt)" class="absolute top-2 right-2 z-30">
                                        <div class="w-6 h-6 bg-primary-500 rounded-full flex items-center justify-center text-surface-950 shadow-lg border-2 border-white/20 animate-pop-in"><i class="fa-solid fa-check text-[10px] font-black"></i></div>
                                    </div>
                                </transition>
                            </div>
                            <div v-if="isSelected(currentStep, opt)" class="w-full p-2 bg-surface-2/50 animate-slide-up border-t border-surface-3/10">
                                <div class="flex flex-col gap-1.5">
                                    <div v-for="(sel, sIdx) in getSelectionsOf(currentStep, opt)" :key="sIdx" class="bg-surface-1 rounded-lg p-2 flex items-center justify-between cursor-pointer hover:bg-surface-3 transition-colors border border-surface-3/10 group/edit" @click.stop="editSelection(currentStep, opt, sel)">
                                        <div class="flex flex-col text-left min-w-0">
                                            <span class="text-[9px] font-black text-primary-400 uppercase tracking-wide mb-0.5">Item {{ getRealIndex(currentStep, sel) }}</span>
                                            <span v-if="sel.modifiers && sel.modifiers.length" class="text-[10px] text-surface-300 truncate w-full leading-tight">{{ sel.modifiers.map(m => m.name).join(', ') }}</span>
                                            <span v-else class="text-[10px] text-surface-500 italic">Padrão</span>
                                        </div>
                                        <div class="w-6 h-6 rounded-full bg-surface-2 flex items-center justify-center text-surface-400 group-hover/edit:text-surface-0 group-hover/edit:bg-surface-3 transition-colors shrink-0"><i class="fa-solid fa-pencil text-[10px]"></i></div>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <template #footer>
        <div class="flex flex-col w-full gap-4 pt-4 bg-surface-1 border-t border-surface-3/10 p-6 pb-8 z-30 shadow-[0_-10px_40px_rgba(0,0,0,0.3)]">
            
             <div v-if="shouldShowTakeoutToggle" class="flex items-center justify-between bg-surface-2 p-3 rounded-[20px] mb-2 border border-transparent hover:border-surface-3/20 transition-colors">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-surface-1 flex items-center justify-center text-primary-500 shadow-sm">
                        <i class="fa-solid fa-bag-shopping"></i>
                    </div>
                    <div class="flex flex-col text-left">
                        <span class="text-sm font-black text-surface-0 uppercase tracking-tight">Para Viagem?</span>
                        <span class="text-[10px] text-surface-500 font-bold uppercase">Embalar p/ levar</span>
                    </div>
                </div>
                <ToggleSwitch v-model="isTakeout" />
            </div>

            <div v-else-if="isForcedTakeout" class="flex justify-center pb-2">
                <span class="text-[9px] font-black uppercase text-surface-500 bg-surface-2 px-3 py-1 rounded-full border border-surface-3/20 flex items-center gap-2">
                    <i class="fa-solid fa-motorcycle"></i> Combo para Viagem / Entrega
                </span>
            </div>

            <div class="flex justify-between items-center w-full gap-4">
                <button class="w-12 h-12 rounded-full bg-surface-2 text-surface-400 hover:text-surface-0 hover:bg-surface-3 flex items-center justify-center transition-all disabled:opacity-30 disabled:cursor-not-allowed" @click="currentStep--" :disabled="currentStep === 0"><i class="fa-solid fa-arrow-left"></i></button>
                <div class="flex-1">
                    <Button v-if="currentStep < steps.length - 1" label="PRÓXIMO" icon="fa-solid fa-arrow-right" iconPos="right" class="!w-full !h-12 !rounded-full !bg-primary-600 hover:!bg-primary-500 !border-none !text-surface-950 !font-black !text-sm !uppercase !tracking-widest shadow-lg shadow-primary-900/20" @click="currentStep++" :disabled="!isStepValid(currentStep)" />
                    <Button v-else label="ADICIONAR AO PEDIDO" icon="fa-solid fa-check" class="!w-full !h-12 !rounded-full !bg-green-500 hover:!bg-green-400 !border-none !text-surface-950 !font-black !text-sm !uppercase !tracking-widest shadow-lg shadow-green-900/20" @click="finishCombo" :disabled="!isStepValid(currentStep)" />
                </div>
            </div>
        </div>
    </template>

    <product-details-modal ref="nestedDetailsModal" :display-mode="displayMode" :show-takeout="false" />
  </component>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useCartStore } from '@/stores/cart-store';
import { useSessionStore } from '@/stores/session-store';
import { useFormat } from '@/composables/useFormat'; 
import { useMobile } from '@/composables/useMobile';
import api from '@/services/api';
import ProductDetailsModal from './ProductDetailsModal.vue';
import Steps from 'primevue/steps';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Drawer from 'primevue/drawer';
import Tag from 'primevue/tag';
import ToggleSwitch from 'primevue/toggleswitch';
import LoadingSpinner from '@/components/shared/LoadingSpinner.vue';

const props = defineProps({ displayMode: { type: String, default: 'center' }, showTakeout: { type: Boolean, default: false } });
const { isMobile } = useMobile();

const componentType = computed(() => isMobile.value ? Drawer : Dialog);
const modalStyle = computed(() => isMobile.value ? { width: '100%', height: '100%', maxHeight: '100%' } : { width: '900px', height: '85vh' });
const ptOptions = computed(() => {
    const common = { header: { class: '!hidden' }, content: { class: '!bg-surface-1 !p-0 !overflow-hidden !flex !flex-col !rounded-t-[32px] md:!rounded-[28px]' }, footer: { class: '!bg-surface-1 !border-t !border-surface-3/10 !p-0' } };
    if (isMobile.value) { return { ...common, root: { class: '!bg-surface-1 !border-none !rounded-t-[32px] !shadow-2xl !overflow-hidden' }, mask: { class: '!bg-surface-base/80 backdrop-blur-md' } }; } else { return { ...common, root: { class: '!bg-surface-1 !border-none !rounded-[28px] !overflow-hidden !shadow-2xl' }, mask: { class: '!bg-surface-base/60 backdrop-blur-sm' } }; }
});

const cartStore = useCartStore();
const sessionStore = useSessionStore();
const { formatCurrency } = useFormat();
const isOpen = ref(false);
const isLoading = ref(true);
const combo = ref(null);
const steps = ref([]);
const currentStep = ref(0);
const selections = ref({}); 
const nestedDetailsModal = ref(null);
const editingIndex = ref(null);
const customCallback = ref(null);
const isTakeout = ref(false);

// Lógica de Takeout (Viagem)
const isForcedTakeout = computed(() => ['delivery', 'pickup', 'counter'].includes(sessionStore.sessionType));
const shouldShowTakeoutToggle = computed(() => sessionStore.sessionType === 'table' && props.showTakeout);

const open = async (comboData, editIdx = null, existingSelections = null, callback = null) => { 
    combo.value = comboData; 
    editingIndex.value = editIdx; 
    customCallback.value = callback; 
    isOpen.value = true; 
    isLoading.value = true; 
    
    if (isForcedTakeout.value) {
        isTakeout.value = true;
    } else {
        isTakeout.value = comboData.is_takeout || false; 
    }
    
    if (editIdx === null) currentStep.value = 0; 
    
    try { 
        const response = await api.get(`/combo-details?id=${comboData.id}`); 
        if (response.data.success) { 
            steps.value = response.data.steps; 
            if (existingSelections) { 
                selections.value = JSON.parse(JSON.stringify(existingSelections)); 
            } else { 
                selections.value = {}; 
                steps.value.forEach((_, idx) => selections.value[idx] = []); 
            } 
        } 
    } catch (error) { console.error(error); } 
    finally { isLoading.value = false; } 
};

// ... (Restante das funções mantidas: handleOptionClick, editSelection, addToSelections, etc.) ...
// Omitido para brevidade, mas deve ser mantido igual ao código anterior.

const handleOptionClick = (stepIdx, opt) => { 
    if (isSelected(stepIdx, opt) && (!opt.modifiers || opt.modifiers.length === 0)) {
        toggleSelection(stepIdx, opt); 
        return; 
    } 
    if (opt.modifiers && opt.modifiers.length > 0) { 
        const productToConfig = { ...opt, price: 0 }; 
        nestedDetailsModal.value.open(productToConfig, (configuredItem) => { 
            addToSelections(stepIdx, opt, configuredItem.modifiers, configuredItem.price, configuredItem.savedSelections); 
        }); 
    } else { 
        addToSelections(stepIdx, opt, [], 0, null); 
    } 
};
const editSelection = (stepIdx, opt, sel) => { 
    if (opt.modifiers && opt.modifiers.length > 0) { 
        const productToConfig = { ...opt, price: 0 }; 
        nestedDetailsModal.value.open(productToConfig, (updatedItem) => { 
            updateSelectionInList(stepIdx, sel, updatedItem); 
        }, null, sel.savedSelections); 
    } 
};
const addToSelections = (stepIdx, baseOption, modifiers, extraPrice, savedSelections) => { 
    const step = steps.value[stepIdx]; 
    const list = selections.value[stepIdx]; 
    const selectionItem = { uniqueId: Date.now() + Math.random(), id: baseOption.id, name: baseOption.name, modifiers: modifiers, extraPrice: extraPrice, savedSelections: savedSelections }; 
    if (list.length >= step.max_qty) { if (step.max_qty === 1) { list.pop(); list.push(selectionItem); } else { alert(`Máximo de ${step.max_qty} itens.`); } } else { list.push(selectionItem); } 
};
const updateSelectionInList = (stepIdx, oldSel, newItem) => { 
    const list = selections.value[stepIdx]; 
    const idx = list.findIndex(i => i.uniqueId === oldSel.uniqueId); 
    if (idx > -1) { list[idx] = { ...list[idx], modifiers: newItem.modifiers, extraPrice: newItem.price, savedSelections: newItem.savedSelections }; } 
};
const toggleSelection = (stepIdx, opt) => { 
    const list = selections.value[stepIdx]; 
    const idx = list.findLastIndex(i => i.id === opt.id); 
    if (idx > -1) list.splice(idx, 1); 
};
const isSelected = (stepIdx, item) => selections.value[stepIdx]?.some(i => i.id === item.id);
const getSelectionsOf = (stepIdx, opt) => selections.value[stepIdx]?.filter(i => i.id === opt.id) || [];
const getRealIndex = (stepIdx, sel) => selections.value[stepIdx].indexOf(sel) + 1;
const isStepValid = (stepIdx) => { 
    const step = steps.value[stepIdx]; 
    if (!step) return false; 
    const selectedCount = selections.value[stepIdx]?.length || 0; 
    const min = (step.min_qty !== undefined) ? step.min_qty : step.max_qty; 
    return selectedCount >= min && selectedCount <= step.max_qty; 
};

const finishCombo = () => { 
    let finalModifiers = []; 
    let totalExtraPrice = 0; 
    for (const key in selections.value) { 
        selections.value[key].forEach(item => { 
            finalModifiers.push({ name: `• ${item.name}`, product_id: item.id, price: item.extraPrice, type: 'combo_item' }); 
            totalExtraPrice += item.extraPrice; 
            if (item.modifiers) { 
                item.modifiers.forEach(mod => { 
                    finalModifiers.push({ name: `   + ${mod.name}`, product_id: mod.id || null, price: mod.price, type: 'combo_modifier' }); 
                    totalExtraPrice += parseFloat(mod.price || 0); 
                }); 
            } 
        }); 
    } 
    if (isTakeout.value) { 
        finalModifiers.push({ name: '[VIAGEM] Embalar p/ levar', price: 0, group: 'Observações' }); 
    } 
    const finalPrice = parseFloat(combo.value.price) + totalExtraPrice;
    const finalItem = { 
        ...combo.value, 
        price: finalPrice, 
        qty: 1, 
        modifiers: finalModifiers, 
        savedSelections: JSON.parse(JSON.stringify(selections.value)), 
        is_takeout: isTakeout.value 
    }; 
    if (customCallback.value) { customCallback.value(finalItem); } 
    else { 
        finalItem.uniqueId = editingIndex.value !== null ? null : Date.now(); 
        if (editingIndex.value !== null) cartStore.updateItem(editingIndex.value, finalItem); 
        else cartStore.addItem(finalItem); 
    } 
    isOpen.value = false; 
};

defineExpose({ open });
</script>

<style scoped>
.animate-pop-in { animation: popIn 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@keyframes popIn { 0% { transform: scale(0); } 100% { transform: scale(1); } }
.animate-fade-in { animation: fadeIn 0.4s ease-out; }
.animate-slide-up { animation: slideUp 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { transform: translateY(10px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
</style>