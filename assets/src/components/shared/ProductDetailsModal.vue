<template>
  <Dialog 
    v-model:visible="isOpen" 
    :modal="true" 
    :position="displayMode === 'bottom' ? 'bottom' : 'center'"
    :dismissableMask="true"
    :closable="false"
    :style="displayMode === 'bottom' ? { width: '100%', margin: '0' } : { width: '600px' }"
    :breakpoints="displayMode === 'bottom' ? null : { '960px': '75vw', '640px': '95vw' }"
    class="bg-surface-900"
    :pt="ptOptions"
  >
    <template #header>
        <div class="relative w-full h-52 overflow-hidden shrink-0 group">
            <div class="absolute inset-0 z-0">
                <img v-if="product?.image" :src="product.image" class="w-full h-full object-cover" />
                <div v-else class="w-full h-full bg-surface-800 flex items-center justify-center text-surface-600">
                    <i class="fa fa-regular fa-image text-4xl"></i>
                </div>
            </div>

            <div class="absolute inset-0 z-10 bg-gradient-to-t from-surface-900 via-surface-900/60 to-transparent"></div>

            <div class="absolute inset-0 z-20 flex flex-col p-4">
                <div class="flex justify-end">
                    <button 
                        class="w-8 h-8 rounded-full text-white flex items-center justify-center hover:bg-black/60 transition-colors active:scale-95"
                        @click="isOpen = false"
                    >
                        <i class="fa fa-times text-lg font-bold"></i>
                    </button>
                </div>

                <div class="mt-auto">
                    <h2 class="text-2xl font-extrabold text-white leading-tight drop-shadow-md">
                        {{ product?.name }}
                    </h2>
                    <p v-if="product?.description" class="text-surface-200 text-sm leading-snug mt-1 line-clamp-2 drop-shadow-sm">
                        {{ product.description }}
                    </p>
                </div>
            </div>

            <div v-if="displayMode === 'bottom'" class="absolute top-3 left-1/2 -translate-x-1/2 w-12 h-1.5 bg-white/30 rounded-full z-30 backdrop-blur-sm"></div>
        </div>
    </template>

    <div v-if="product" class="flex flex-col h-full" :class="displayMode === 'bottom' ? 'max-h-[85vh]' : 'max-h-[70vh]'">
      
      <div class="flex-1 overflow-y-auto p-5 space-y-6 scrollbar-hide no-gutter">
        
        <div v-for="group in productGroups" :key="group.id" class="bg-surface-950 rounded-xl p-4">
            
            <div class="flex flex-col gap-3 mb-4">
                <div class="flex justify-between items-end">
                    <div>
                        <div class="font-bold text-lg text-white">{{ group.title }}</div>
                        <div class="text-[10px] font-bold uppercase tracking-wider" 
                             :class="validateGroup(group) ? 'text-primary-400' : 'text-red-400'">
                            {{ getGroupStatus(group) }}
                        </div>
                    </div>
                    <Button 
                        v-if="hasSelection(group)" 
                        icon="fa fa-solid fa-eraser" 
                        text rounded 
                        class="!w-8 !h-8 !text-surface-400 hover:!text-white"
                        @click="clearGroup(group)"
                    />
                </div>

                <div class="w-full h-1.5 bg-surface-800 rounded-full overflow-hidden flex">
                    <div 
                        v-for="(segment, idx) in calculateProgressSegments(group)" 
                        :key="idx"
                        :style="{ width: segment.width + '%' }"
                        :class="segment.color"
                        class="h-full transition-all duration-300"
                    ></div>
                </div>
            </div>

            <div v-if="isIngredientGroup(group)" class="flex flex-wrap gap-2">
                <div 
                    v-for="item in getSortedItems(group)" 
                    :key="item.name" 
                    class="cursor-pointer rounded-full px-3 py-1.5 transition-all duration-300 flex items-center select-none text-sm font-bold"
                    :class="isSelected(group, item) 
                        ? 'bg-white text-surface-950 shadow-md shadow-primary-900/30' 
                        : 'bg-surface-900 text-white  hover:border-surface-600 hover:text-surface-200'"
                    @click="toggleSelection(group, item)"
                >
                    <i v-if="isSelected(group, item)" class="fa fa-solid fa-check mr-1.5 text-[10px]"></i>
                    <span>{{ item.name }}</span>
                    <span v-if="calculateItemVisualPrice(group, item) > 0" class="ml-1.5 text-[10px] opacity-70">
                        + R$ {{ formatMoney(calculateItemVisualPrice(group, item)) }}
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
                        <Checkbox v-if="group.type === 'adicional'" :modelValue="isSelected(group, item)" :binary="true" readonly />
                        <RadioButton v-else v-model="selections[group.id]" :value="item" />
                    </div>
                    <label class="flex-1 cursor-pointer font-medium text-surface-200">{{ item.name }}</label>
                    <span v-if="item.price > 0" class="text-primary-400 font-bold text-sm">+ R$ {{ formatMoney(item.price) }}</span>
                </div>
            </div>
        </div>
      </div>
    </div>

    <template #footer>
        <div class="flex items-center justify-between w-full pt-2 gap-2">
            <div class="flex items-center bg-surface-950 rounded-full  h-12 shrink-0 shadow-sm">
                <button class="w-10 h-full flex items-center justify-center text-surface-400 hover:text-white transition-colors rounded-l-full active:bg-surface-800" @click="decrementQty">
                    <i class="fa fa-solid fa-minus text-xs"></i>
                </button>
                <span class="w-6 text-center font-bold text-white text-lg select-none">{{ quantity }}</span>
                <button class="w-10 h-full flex items-center justify-center text-surface-400 hover:text-white transition-colors rounded-r-full active:bg-surface-800" @click="incrementQty">
                    <i class="fa fa-solid fa-plus text-xs"></i>
                </button>
            </div>
            <div class="font-bold text-xl text-white">R$ {{ formatMoney(currentTotal * quantity) }}</div>
            <button 
                class="h-12 bg-white hover:bg-primary-500 rounded-full px-6 transition-all active:scale-[0.98] shadow-lg shadow-primary-900/50 disabled:opacity-50 disabled:cursor-not-allowed text-surface-950 font-black text-base uppercase tracking-wide min-w-[100px]"
                :disabled="!isValid"
                @click="confirmAdd"
            >
                {{ editingIndex !== null ? 'Salvar' : 'Quero' }}
            </button>
        </div>
    </template>
  </Dialog>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useCartStore } from '@/stores/cart-store';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Checkbox from 'primevue/checkbox';
import RadioButton from 'primevue/radiobutton';

const props = defineProps({
    visible: Boolean,
    product: Object,
    displayMode: { type: String, default: 'center' } 
});

const ptOptions = computed(() => {
    const base = {
        header: { class: '!bg-surface-900 !border-0 !p-0 relative !justify-center overflow-hidden' },
        content: { class: '!bg-surface-900 !p-0 !overflow-hidden' },
        footer: { class: '!bg-surface-900 !border-t !border-surface-800 !p-4 !pb-6' }, 
    };
    if (props.displayMode === 'bottom') {
        return { ...base, root: { class: '!bg-surface-900 !border-t !border-surface-700 !rounded-t-[2rem]' }, mask: { class: '!bg-black/80 backdrop-blur-sm' } };
    }
    return { ...base, root: { class: '!bg-surface-900 !border !border-surface-800 !rounded-2xl' }, mask: { class: '!bg-black/60 backdrop-blur-sm' } };
});

const cartStore = useCartStore();
const isOpen = ref(false);
const product = ref(null);
const quantity = ref(1); 
const selections = ref({}); 
const onConfirmCallback = ref(null);
const editingIndex = ref(null);

const formatMoney = (val) => val.toFixed(2).replace('.', ',');
const isIngredientGroup = (g) => g.type === 'sabor' || g.type === 'ingrediente';

// NOVA FUNÇÃO: Ordenação Dinâmica (Selecionados Primeiro + Alfabeto)
const getSortedItems = (group) => {
    if (!group.items) return [];
    
    return [...group.items].sort((a, b) => {
        const aSel = isSelected(group, a);
        const bSel = isSelected(group, b);

        // Se um está selecionado e o outro não, o selecionado vem primeiro
        if (aSel && !bSel) return -1;
        if (!aSel && bSel) return 1;

        // Se ambos estão no mesmo estado de seleção, ordena por nome (alfabético)
        return a.name.localeCompare(b.name);
    });
};

const productGroups = computed(() => product.value?.originalModifiers || product.value?.modifiers || []);

const calculateProgressSegments = (group) => {
    const segments = [];
    const sel = selections.value[group.id];
    const currentQty = Array.isArray(sel) ? sel.length : (sel ? 1 : 0);
    const max = group.max || Math.max(currentQty, group.min, group.free_limit) || 10;

    if (currentQty > 0) {
        if (currentQty < group.min) {
            segments.push({ width: (currentQty / max) * 100, color: 'bg-red-500' });
        } else {
            const qtyGratis = Math.min(currentQty, group.free_limit || group.min || max);
            segments.push({ width: (qtyGratis / max) * 100, color: 'bg-primary-500' });
            
            if (currentQty > group.free_limit && group.free_limit > 0) {
                const qtyPaga = currentQty - group.free_limit;
                segments.push({ width: (qtyPaga / max) * 100, color: 'bg-white' });
            }
        }
    }
    return segments;
};

const getGroupStatus = (g) => {
    const sel = selections.value[g.id];
    const count = Array.isArray(sel) ? sel.length : (sel ? 1 : 0);
    if (count < g.min) return `Selecione pelo menos ${g.min}`;
    if (g.max && count >= g.max) return 'Limite atingido';
    return 'Tudo certo';
};

const open = (p, callback = null, editIdx = null, existingSelections = null) => {
  product.value = JSON.parse(JSON.stringify(p));
  onConfirmCallback.value = callback;
  editingIndex.value = editIdx;
  quantity.value = (p.qty && editIdx !== null) ? p.qty : 1;
  selections.value = existingSelections ? JSON.parse(JSON.stringify(existingSelections)) : {};
  if (!existingSelections && productGroups.value) {
      productGroups.value.forEach(g => { selections.value[g.id] = (g.type === 'opcao') ? null : []; });
  }
  isOpen.value = true;
};

const incrementQty = () => quantity.value++;
const decrementQty = () => { if(quantity.value > 1) quantity.value--; };
const clearGroup = (group) => selections.value[group.id] = Array.isArray(selections.value[group.id]) ? [] : null;
const hasSelection = (group) => { const s = selections.value[group.id]; return Array.isArray(s) ? s.length > 0 : !!s; };
const isSelected = (group, item) => { const s = selections.value[group.id]; return Array.isArray(s) ? s.some(i => i.name === item.name) : s?.name === item.name; };

const toggleSelection = (group, item) => {
  const list = selections.value[group.id];
  const idx = list.findIndex(i => i.name === item.name);
  if (idx > -1) list.splice(idx, 1);
  else if (!group.max || list.length < group.max) list.push(item);
};

const calculateItemVisualPrice = (group, item) => {
  let cost = parseFloat(item.price || 0);
  if (!isIngredientGroup(group)) return cost;
  const currentList = selections.value[group.id] || [];
  const selectedIndex = currentList.findIndex(i => i.name === item.name);
  const pos = selectedIndex > -1 ? selectedIndex : currentList.length;
  if (group.free_limit > 0 && pos >= group.free_limit) cost += parseFloat(group.increment || 0);
  return cost;
};

const currentTotal = computed(() => {
  let total = parseFloat(product.value?.price || 0);
  for (const key in selections.value) {
    const val = selections.value[key];
    const group = productGroups.value.find(g => g.id == key);
    if (!group || !val) continue;
    if (Array.isArray(val)) {
      val.forEach((item, idx) => {
        let p = parseFloat(item.price || 0);
        if (isIngredientGroup(group) && idx >= group.free_limit) p += parseFloat(group.increment || 0);
        total += p;
      });
    } else total += parseFloat(val.price || 0);
  }
  return total;
});

const validateGroup = (g) => {
  const sel = selections.value[g.id];
  return Array.isArray(sel) ? sel.length >= g.min : (g.min === 0 || !!sel);
};

const isValid = computed(() => productGroups.value.every(g => validateGroup(g)));

const confirmAdd = () => {
  let flatModifiers = [];
  for (const key in selections.value) {
    const val = selections.value[key];
    const group = productGroups.value.find(g => g.id == key);
    if (!val || !group) continue;
    if (Array.isArray(val)) {
       val.forEach((item, idx) => {
          let p = parseFloat(item.price || 0);
          if (isIngredientGroup(group) && idx >= group.free_limit) p += parseFloat(group.increment || 0);
          flatModifiers.push({ ...item, price: p, group: group.title });
       });
    } else flatModifiers.push({ ...val, group: group.title });
  }
  const finalItem = { ...product.value, price: currentTotal.value, qty: quantity.value, modifiers: flatModifiers, originalModifiers: productGroups.value, savedSelections: JSON.parse(JSON.stringify(selections.value)) };
  if (onConfirmCallback.value) onConfirmCallback.value(finalItem);
  else editingIndex.value !== null ? cartStore.updateItem(editingIndex.value, finalItem) : cartStore.addItem(finalItem);
  isOpen.value = false;
};

defineExpose({ open });
</script>

<style scoped>
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.scrollbar-hide::-webkit-scrollbar { display: none; }
.no-gutter { scrollbar-gutter: auto; }
</style>