<template>
  <Dialog 
    v-model:visible="isOpen" 
    :modal="true" 
    :style="{ width: '600px' }" 
    :breakpoints="{ '960px': '75vw', '640px': '95vw' }"
    class="bg-surface-900"
    :pt="{
        root: { class: '!bg-surface-900 !border !border-surface-800 !rounded-2xl' },
        header: { class: '!bg-surface-900 !border-b !border-surface-800 !p-5' },
        content: { class: '!bg-surface-900 !p-0' },
        footer: { class: '!bg-surface-900 !border-t !border-surface-800 !p-5' },
        closeButton: { class: '!text-surface-400 hover:!text-white hover:!bg-surface-800' }
    }"
  >
    <template #header>
        <span class="text-xl font-bold text-white">{{ product?.name }}</span>
    </template>

    <div v-if="product" class="flex flex-col h-full max-h-[70vh]">
      
      <div class="relative w-full h-48 bg-surface-800 shrink-0">
         <img v-if="product.image" :src="product.image" class="w-full h-full object-cover opacity-80" />
         <div v-else class="flex items-center justify-center h-full text-surface-600">
            <i class="pi pi-image text-5xl"></i>
         </div>
         <div class="absolute bottom-0 left-0 w-full bg-gradient-to-t from-black/90 to-transparent p-4 flex justify-between items-end">
            <span class="text-white text-sm font-bold uppercase tracking-wider">Preço Base</span>
            <span class="text-2xl font-bold text-primary-400">R$ {{ formatMoney(basePrice) }}</span>
         </div>
      </div>

      <div class="flex-1 overflow-y-auto p-5 space-y-6 scrollbar-thin">
        
        <div v-for="group in productGroups" :key="group.id" class="bg-surface-950 border border-surface-800 rounded-xl p-4">
            
            <div class="flex justify-between items-start mb-4">
                <div>
                    <div class="font-bold text-lg text-white">{{ group.title }}</div>
                    <div class="text-xs text-primary-300 font-bold uppercase tracking-wide mt-1">{{ getGroupSubtitle(group) }}</div>
                </div>
                <div class="flex items-center gap-2">
                    <Button 
                        v-if="hasSelection(group)" 
                        icon="pi pi-eraser" 
                        text rounded 
                        class="!w-8 !h-8 !text-surface-400 hover:!text-white"
                        @click="clearGroup(group)"
                        v-tooltip="'Limpar'"
                    />
                    <Tag :value="getGroupStatus(group)" :severity="validateGroup(group) ? 'success' : 'warn'" />
                </div>
            </div>

            <div v-if="isIngredientGroup(group)" class="flex flex-wrap gap-2">
                <div 
                    v-for="(item, idx) in group.items" 
                    :key="idx"
                    class="cursor-pointer rounded-lg px-4 py-3 border transition-all duration-200 flex items-center select-none"
                    :class="isSelected(group, item) ? 'bg-primary-600 text-white border-primary-500 shadow-lg shadow-primary-900/50' : 'bg-surface-900 text-surface-400 border-surface-800 hover:border-surface-600 hover:text-surface-200'"
                    @click="toggleSelection(group, item)"
                >
                    <i v-if="isSelected(group, item)" class="pi pi-check mr-2 text-xs"></i>
                    <span class="font-medium text-sm">{{ item.name }}</span>
                    <span v-if="calculateItemVisualPrice(group, item) > 0" class="ml-2 text-xs opacity-80 font-bold">
                        +{{ formatMoney(calculateItemVisualPrice(group, item)) }}
                    </span>
                </div>
            </div>

            <div v-else class="flex flex-col gap-2">
                <div 
                    v-for="(item, idx) in group.items" 
                    :key="idx"
                    class="flex items-center p-3 rounded-lg border transition-colors cursor-pointer hover:bg-surface-900"
                    :class="isSelected(group, item) ? 'bg-surface-900 border-primary-500' : 'border-transparent'"
                    @click="group.type === 'adicional' ? toggleSelection(group, item) : (selections[group.id] = item)"
                >
                    <div class="mr-3 flex items-center">
                        <Checkbox 
                            v-if="group.type === 'adicional'" 
                            :modelValue="isSelected(group, item)" 
                            :binary="true" readonly
                        />
                        <RadioButton 
                            v-else 
                            v-model="selections[group.id]" 
                            :value="item" 
                        />
                    </div>
                    
                    <label class="flex-1 cursor-pointer font-medium text-surface-200">{{ item.name }}</label>
                    <span v-if="item.price > 0" class="text-primary-400 font-bold text-sm">+ {{ formatMoney(item.price) }}</span>
                </div>
            </div>

        </div>
      </div>
    </div>

    <template #footer>
        <div class="flex justify-between items-center w-full">
            <div class="flex flex-col">
                <span class="text-xs text-surface-400 uppercase font-bold">Total Final</span>
                <span class="text-2xl font-bold text-primary-400">R$ {{ formatMoney(currentTotal) }}</span>
            </div>
            <Button 
                :label="editingIndex !== null ? 'SALVAR ALTERAÇÕES' : 'ADICIONAR AO PEDIDO'" 
                icon="pi pi-check" 
                :disabled="!isValid" 
                @click="confirmAdd"
                class="!font-bold !bg-primary-600 hover:!bg-primary-500 !border-none !px-6 !py-3"
            />
        </div>
    </template>

  </Dialog>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useCartStore } from '@/stores/cart-store';

const cartStore = useCartStore();
const isOpen = ref(false);
const product = ref(null);
const selections = ref({}); 
const onConfirmCallback = ref(null);
const editingIndex = ref(null);

const basePrice = computed(() => product.value ? parseFloat(product.value.price || 0) : 0);
const formatMoney = (val) => val.toFixed(2).replace('.', ',');
const isIngredientGroup = (g) => g.type === 'sabor' || g.type === 'ingrediente';

const productGroups = computed(() => {
  if (!product.value) return [];
  return product.value.originalModifiers || product.value.modifiers || [];
});

const open = (p, callback = null, editIdx = null, existingSelections = null) => {
  product.value = JSON.parse(JSON.stringify(p));
  onConfirmCallback.value = callback;
  editingIndex.value = editIdx;
  
  if (existingSelections) {
    selections.value = JSON.parse(JSON.stringify(existingSelections));
  } else {
    selections.value = {}; 
    if (productGroups.value) {
      productGroups.value.forEach(g => {
        selections.value[g.id] = (g.type === 'opcao') ? null : [];
      });
    }
  }
  
  isOpen.value = true;
};

const clearGroup = (group) => { if (Array.isArray(selections.value[group.id])) selections.value[group.id] = []; else selections.value[group.id] = null; };
const hasSelection = (group) => { const sel = selections.value[group.id]; return Array.isArray(sel) ? sel.length > 0 : !!sel; };
const isSelected = (group, item) => { const sel = selections.value[group.id]; return Array.isArray(sel) ? sel.some(i => i.name === item.name) : (sel && sel.name === item.name); };
const toggleSelection = (group, item) => {
  const list = selections.value[group.id];
  const idx = list.findIndex(i => i.name === item.name);
  if (idx > -1) list.splice(idx, 1);
  else {
    if (group.max > 0 && list.length >= group.max) { alert('Máximo atingido!'); return; }
    list.push(item);
  }
};
const calculateItemVisualPrice = (group, item) => {
  let cost = parseFloat(item.price || 0);
  if (!isIngredientGroup(group)) return cost;
  const currentList = selections.value[group.id] || [];
  const selectedIndex = currentList.findIndex(i => i.name === item.name);
  if (selectedIndex > -1) {
    if (group.free_limit > 0 && selectedIndex >= group.free_limit) cost += parseFloat(group.increment || 0);
    return cost;
  }
  if (group.free_limit > 0 && currentList.length >= group.free_limit) cost += parseFloat(group.increment || 0);
  return cost;
};
const currentTotal = computed(() => {
  let total = basePrice.value;
  for (const key in selections.value) {
    const val = selections.value[key];
    const group = productGroups.value.find(g => g.id == key);
    if (!group) continue;
    if (Array.isArray(val)) {
      if (isIngredientGroup(group)) {
        let groupBaseSum = val.reduce((acc, item) => acc + parseFloat(item.price || 0), 0);
        let count = val.length;
        let excess = Math.max(0, count - group.free_limit);
        let incrementTotal = excess * parseFloat(group.increment || 0);
        total += groupBaseSum + incrementTotal;
      } else {
        val.forEach(item => total += parseFloat(item.price || 0));
      }
    } else if (val) {
      total += parseFloat(val.price || 0);
    }
  }
  return total;
});
const getGroupSubtitle = (g) => {
  if (g.type === 'opcao') return 'ESCOLHA 1 OPÇÃO';
  let texts = [];
  if (g.min > 0) texts.push(`MÍNIMO: ${g.min}`);
  if (g.max > 0) texts.push(`MÁXIMO: ${g.max}`);
  if (g.free_limit > 0) texts.push(`GRÁTIS: ${g.free_limit}`);
  return texts.join(' • ') || 'OPCIONAL';
};
const validateGroup = (g) => {
  const sel = selections.value[g.id];
  if (!sel) return g.min === 0;
  if (Array.isArray(sel)) return sel.length >= g.min;
  return true;
};
const getGroupStatus = (g) => {
  if (validateGroup(g)) return 'OK';
  const sel = selections.value[g.id];
  const count = Array.isArray(sel) ? sel.length : (sel ? 1 : 0);
  return `FALTAM ${g.min - count}`;
};
const isValid = computed(() => productGroups.value.every(g => validateGroup(g)));

const confirmAdd = () => {
  let flatModifiers = [];
  for (const key in selections.value) {
    const val = selections.value[key];
    const group = productGroups.value.find(g => g.id == key);
    if (Array.isArray(val)) {
       if (isIngredientGroup(group)) {
         val.forEach((item, index) => {
            let adjustedPrice = parseFloat(item.price || 0);
            if (index >= group.free_limit) adjustedPrice += parseFloat(group.increment || 0);
            flatModifiers.push({ ...item, price: adjustedPrice, group: group.title });
         });
       } else {
         val.forEach(item => flatModifiers.push({ ...item, group: group.title }));
       }
    } else if (val) flatModifiers.push({ ...val, group: group.title });
  }

  const extraPrice = flatModifiers.reduce((acc, m) => acc + parseFloat(m.price || 0), 0);
  const finalItem = {
      ...product.value, 
      price: parseFloat(product.value.price || 0) + extraPrice, 
      modifiers: flatModifiers, 
      originalModifiers: productGroups.value, 
      savedSelections: JSON.parse(JSON.stringify(selections.value))
  };

  if (onConfirmCallback.value) onConfirmCallback.value({ ...finalItem, extraPrice: extraPrice });
  else {
      if (editingIndex.value !== null) cartStore.updateItem(editingIndex.value, finalItem);
      else cartStore.addItem(finalItem);
  }
  isOpen.value = false;
};

defineExpose({ open });
</script>