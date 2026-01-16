<template>
  <Dialog 
    v-model:visible="isOpen" 
    :modal="true" 
    :style="{ width: '900px' }" 
    :breakpoints="{ '960px': '90vw', '640px': '100vw' }"
    class="bg-surface-900"
    :pt="{
        root: { class: '!bg-surface-900 !border !border-surface-800 !rounded-2xl' },
        header: { class: '!bg-surface-900 !border-b !border-surface-800 !p-6' },
        content: { class: '!bg-surface-900 !p-0' },
        footer: { class: '!bg-surface-900 !border-t !border-surface-800 !p-6' },
        closeButton: { class: '!text-surface-400 hover:!text-white hover:!bg-surface-800' }
    }"
  >
    <template #header>
        <div class="flex items-center justify-between w-full">
            <div class="flex flex-col">
                <span class="text-xl font-bold text-white">{{ combo?.name }}</span>
                <span class="text-primary-400 font-bold text-lg">R$ {{ combo?.price?.toFixed(2).replace('.', ',') }}</span>
            </div>
        </div>
    </template>

    <div class="flex flex-col h-full" style="min-height: 60vh; max-height: 70vh;">
        
        <div class="bg-surface-950 p-4 border-b border-surface-800">
            <Steps 
                :model="stepsItems" 
                :readonly="true" 
                v-model:activeStep="currentStep" 
                class="w-full"
                :pt="{
                    menuitem: ({ context }) => ({
                        class: context.active ? '!text-primary-500 !font-bold' : '!text-surface-500'
                    })
                }"
            />
        </div>

        <div v-if="isLoading" class="flex-1 flex items-center justify-center">
            <ProgressSpinner />
        </div>

        <div v-else class="flex-1 overflow-y-auto p-6 scrollbar-thin bg-surface-900">
            
            <div v-if="steps[currentStep]" class="animate-fade-in">
                <div class="flex justify-between items-end mb-6">
                    <div>
                        <div class="text-2xl font-bold text-white mb-1">{{ steps[currentStep].title }}</div>
                        <div class="text-sm text-surface-400">Escolha os itens desta etapa</div>
                    </div>
                    <Tag severity="info" class="!bg-surface-800 !text-primary-300 !text-sm !font-bold">
                        Selecionados: {{ selections[currentStep]?.length || 0 }} / {{ steps[currentStep].max_qty }}
                    </Tag>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="opt in steps[currentStep].options" :key="opt.id">
                        <div 
                            class="relative h-full border rounded-2xl p-4 cursor-pointer transition-all duration-200 flex flex-col hover:-translate-y-1 hover:shadow-lg"
                            :class="isSelected(currentStep, opt) ? 'bg-surface-800 border-primary-500 ring-1 ring-primary-500' : 'bg-surface-950 border-surface-800 hover:border-surface-600'"
                            @click="handleOptionClick(currentStep, opt)"
                        >
                            <div v-if="opt.image" class="w-full h-32 bg-cover bg-center rounded-xl mb-3 bg-surface-800" :style="`background-image: url(${opt.image})`"></div>
                            
                            <div class="font-bold text-center text-lg mb-2 text-surface-200">{{ opt.name }}</div>

                            <div v-if="isSelected(currentStep, opt)" class="flex flex-col gap-2 mt-auto pt-3 border-t border-surface-700">
                                <div 
                                    v-for="(sel, sIdx) in getSelectionsOf(currentStep, opt)" 
                                    :key="sIdx"
                                    class="bg-surface-900 border border-surface-700 rounded-lg p-2 text-xs relative cursor-pointer hover:bg-surface-700 hover:text-white transition-colors"
                                    @click.stop="editSelection(currentStep, opt, sel)"
                                >
                                    <div class="font-bold text-primary-400 mb-1 flex items-center justify-between">
                                        <span>Item #{{ getRealIndex(currentStep, sel) }}</span>
                                        <i class="pi pi-pencil text-[10px]"></i>
                                    </div>
                                    <div v-if="sel.modifiers && sel.modifiers.length">
                                        <div v-for="mod in sel.modifiers" :key="mod.name" class="text-surface-400 truncate">
                                            + {{ mod.name }}
                                        </div>
                                    </div>
                                    <div v-else class="text-surface-500 italic">Padrão</div>
                                </div>
                            </div>
                            
                            <div v-if="isSelected(currentStep, opt)" class="absolute top-2 right-2 w-6 h-6 bg-primary-500 rounded-full flex items-center justify-center text-white shadow-lg">
                                <i class="pi pi-check text-xs font-bold"></i>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <template #footer>
        <div class="flex justify-between items-center w-full pt-2">
            <Button 
                label="Voltar" 
                icon="pi pi-arrow-left" 
                text 
                class="!text-surface-400 hover:!text-white"
                @click="currentStep--" 
                :disabled="currentStep === 0"
            />
            
            <div class="flex-1"></div>

            <Button 
                v-if="currentStep < steps.length - 1"
                label="Próximo Passo" 
                icon="pi pi-arrow-right" 
                iconPos="right"
                class="!px-6 !font-bold"
                @click="currentStep++" 
                :disabled="!isStepValid(currentStep)"
            />

            <Button 
                v-else
                label="FINALIZAR COMBO" 
                icon="pi pi-check" 
                class="!bg-green-600 hover:!bg-green-500 !border-none !text-white !font-bold !px-6"
                @click="finishCombo" 
                :disabled="!isStepValid(currentStep)"
            />
        </div>
    </template>

    <product-details-modal ref="nestedDetailsModal" />
  </Dialog>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useCartStore } from '@/stores/cart-store';
import api from '@/services/api';
import ProductDetailsModal from './ProductDetailsModal.vue'; 
import Steps from 'primevue/steps'; // Import explícito às vezes ajuda

const cartStore = useCartStore();
const isOpen = ref(false);
const isLoading = ref(true);
const combo = ref(null);
const steps = ref([]);
const currentStep = ref(0);
const selections = ref({}); 
const nestedDetailsModal = ref(null);
const editingIndex = ref(null); 

const stepsItems = computed(() => {
    return steps.value.map((s, idx) => ({
        label: `Etapa ${idx + 1}`
    }));
});

const open = async (comboData, editIdx = null, existingSelections = null) => {
  combo.value = comboData;
  editingIndex.value = editIdx;
  isOpen.value = true;
  isLoading.value = true;
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
  } catch (error) {
    console.error(error);
  } finally {
    isLoading.value = false;
  }
};

const handleOptionClick = (stepIdx, opt) => {
  if (isSelected(stepIdx, opt)) { toggleSelection(stepIdx, opt); return; }
  
  // Se o item tem modificadores, abre o modal de detalhes
  if (opt.modifiers && opt.modifiers.length > 0) {
    const productToConfig = { ...opt, price: 0 }; 
    nestedDetailsModal.value.open(productToConfig, (configuredItem) => {
      addToSelections(stepIdx, opt, configuredItem.selectedModifiers, configuredItem.extraPrice, configuredItem.savedSelections);
    });
  } else { 
      // Se não, adiciona direto
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
  
  if (list.length >= step.max_qty) {
    if (step.max_qty === 1) { list.pop(); list.push(selectionItem); }
    else { alert(`Máximo de ${step.max_qty} itens.`); }
  } else { list.push(selectionItem); }
};

const updateSelectionInList = (stepIdx, oldSel, newItem) => {
  const list = selections.value[stepIdx];
  const idx = list.findIndex(i => i.uniqueId === oldSel.uniqueId);
  if (idx > -1) { list[idx] = { ...list[idx], modifiers: newItem.selectedModifiers, extraPrice: newItem.extraPrice, savedSelections: newItem.savedSelections }; }
};

const toggleSelection = (stepIdx, opt) => {
  const list = selections.value[stepIdx];
  const idx = list.findLastIndex(i => i.id === opt.id);
  if (idx > -1) list.splice(idx, 1);
};

const isSelected = (stepIdx, item) => selections.value[stepIdx]?.some(i => i.id === item.id);
const getSelectionsOf = (stepIdx, opt) => selections.value[stepIdx]?.filter(i => i.id === opt.id) || [];
const getRealIndex = (stepIdx, sel) => selections.value[stepIdx].indexOf(sel) + 1;
const isStepValid = (stepIdx) => (selections.value[stepIdx]?.length || 0) === steps.value[stepIdx].max_qty; 

const finishCombo = () => {
  let finalModifiers = [];
  let totalExtraPrice = 0;
  for (const key in selections.value) {
    selections.value[key].forEach(item => {
      finalModifiers.push({ name: `• ${item.name}`, price: item.extraPrice });
      totalExtraPrice += item.extraPrice;
      if (item.modifiers) {
        item.modifiers.forEach(mod => {
           finalModifiers.push({ name: `   + ${mod.name}`, price: mod.price });
           totalExtraPrice += parseFloat(mod.price || 0);
        });
      }
    });
  }
  const cartItem = {
    ...combo.value, 
    price: parseFloat(combo.value.price) + totalExtraPrice, 
    uniqueId: editingIndex.value !== null ? null : Date.now(), 
    modifiers: finalModifiers,
    savedSelections: JSON.parse(JSON.stringify(selections.value))
  };
  if (editingIndex.value !== null) cartStore.updateItem(editingIndex.value, cartItem);
  else cartStore.addItem(cartItem);
  isOpen.value = false;
};

defineExpose({ open });
</script>