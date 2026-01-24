<template>
  <Dialog 
    v-model:visible="isOpen" 
    modal 
    position="bottom" 
    :dismissableMask="true"
    class="!w-full !m-0 !max-w-md !mx-auto !bg-transparent !shadow-none"
    :contentStyle="{ padding: '0', background: 'transparent' }"
    :pt="{
        mask: { class: '!bg-surface-950/80 backdrop-blur-sm' },
        root: { class: '!border-0 !rounded-none' }
    }"
  >
    <div class="bg-surface-900/80 backdrop-blur-xl border-t border-white/10 rounded-t-[2.5rem] shadow-[0_-10px_40px_rgba(0,0,0,0.6)] flex flex-col max-h-[85vh] relative overflow-hidden animate-slide-up">
        
        <div class="p-6 pb-2 shrink-0 relative z-20">
            <div class="w-12 h-1.5 bg-surface-700/50 rounded-full mx-auto mb-4"></div>
            <div class="flex justify-between items-end">
                <div>
                    <h3 class="text-xl font-black text-white tracking-tight">Pedir Novamente</h3>
                    <p class="text-surface-400 text-sm">Confirme os itens para adicionar ao carrinho.</p>
                </div>
                <div v-if="!isLoading" class="text-right">
                    <div class="text-xs text-surface-400 font-bold uppercase tracking-wider">Total Atual</div>
                    <div class="text-xl font-bold text-primary-400">R$ {{ formatMoney(totalSelected) }}</div>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto p-6 pt-2 space-y-3 scrollbar-thin relative z-10">
            
            <div v-if="isLoading" class="flex flex-col items-center justify-center py-10 space-y-4">
                <i class="fa-solid fa-circle-notch fa-spin text-3xl text-primary-500"></i>
                <span class="text-surface-400 text-sm animate-pulse">Verificando disponibilidade...</span>
            </div>

            <div v-else-if="items.length === 0" class="text-center py-10 text-surface-500">
                Não foi possível recuperar os itens deste pedido.
            </div>

            <div v-else v-for="(item, index) in items" :key="index" 
                 class="group relative flex items-start gap-3 p-4 rounded-2xl border transition-all duration-300"
                 :class="item.isAvailable && item.selected 
                    ? 'bg-surface-800/60 border-primary-500/50 shadow-lg shadow-primary-500/10' 
                    : 'bg-surface-900/40 border-white/5 opacity-60'"
                 @click="toggleSelection(item)"
            >
                <div class="mt-1 w-6 h-6 rounded-full border-2 flex items-center justify-center transition-colors shrink-0"
                     :class="item.selected && item.isAvailable ? 'bg-primary-500 border-primary-500' : 'border-surface-600'">
                    <i v-if="item.selected && item.isAvailable" class="fa-solid fa-check text-white text-xs"></i>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-start">
                        <span class="font-bold text-white text-sm leading-tight line-clamp-2" :class="{'line-through text-surface-500': !item.isAvailable}">
                            {{ item.qty }}x {{ item.name }}
                        </span>
                        <span class="font-mono font-bold text-sm ml-2" :class="item.isAvailable ? 'text-white' : 'text-surface-600'">
                            R$ {{ formatMoney(item.currentPrice * item.qty) }}
                        </span>
                    </div>

                    <div v-if="item.modifiers && item.modifiers.length" class="mt-1 pl-2 border-l-2 border-surface-700/50">
                        <div v-for="mod in item.modifiers" :key="mod.name" class="text-xs text-surface-400 truncate">
                            + {{ mod.name }}
                        </div>
                    </div>

                    <div v-if="!item.isAvailable" class="mt-2 inline-flex items-center gap-1 text-[10px] font-bold uppercase text-red-400 bg-red-500/10 px-2 py-0.5 rounded">
                        <i class="fa-solid fa-ban"></i> Indisponível no momento
                    </div>
                    <div v-else-if="item.priceChanged" class="mt-2 inline-flex items-center gap-1 text-[10px] font-bold uppercase text-yellow-400 bg-yellow-500/10 px-2 py-0.5 rounded">
                        <i class="fa-solid fa-triangle-exclamation"></i> Preço alterado
                    </div>
                </div>
            </div>
        </div>

        <div class="p-6 pt-4 border-t border-white/5 relative z-20 bg-surface-900/90 backdrop-blur-xl">
            <button 
                class="w-full h-14 rounded-xl bg-primary-500 hover:bg-primary-400 text-surface-950 font-black text-lg uppercase tracking-wide shadow-[0_0_20px_rgba(16,185,129,0.3)] transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-3"
                :disabled="isLoading || selectedCount === 0"
                @click="confirmReorder"
            >
                <span>Adicionar ao Carrinho</span>
                <span v-if="selectedCount > 0" class="bg-surface-950/20 px-2 py-0.5 rounded text-sm min-w-[24px]">{{ selectedCount }}</span>
            </button>
            <button class="w-full mt-3 text-surface-500 font-bold text-xs uppercase tracking-widest hover:text-white transition-colors" @click="isOpen = false">
                Cancelar
            </button>
        </div>

    </div>
  </Dialog>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useCartStore } from '@/stores/cart-store';
import { useMenuStore } from '@/stores/menu-store';
import api from '@/services/api';
import { notify } from '@/services/notify';
import Dialog from 'primevue/dialog';

const cartStore = useCartStore();
const menuStore = useMenuStore();

const isOpen = ref(false);
const isLoading = ref(false);
const items = ref([]);

// Formatação
const formatMoney = (val) => parseFloat(val || 0).toFixed(2).replace('.', ',');

// Computados
const selectedCount = computed(() => items.value.filter(i => i.selected && i.isAvailable).length);
const totalSelected = computed(() => {
    return items.value
        .filter(i => i.selected && i.isAvailable)
        .reduce((acc, item) => acc + (item.currentPrice * item.qty), 0);
});

// Abertura do Modal
const open = async (orderId) => {
    isOpen.value = true;
    isLoading.value = true;
    items.value = [];

    try {
        // 1. Garante que temos o menu atualizado para checar estoque/preço
        if (menuStore.categories.length === 0) {
            await menuStore.fetchMenu();
        }

        // 2. Busca os itens do pedido original
        const { data } = await api.get(`/order-details/${orderId}`);
        
        if (data.success && data.items) {
            // 3. Processa e valida cada item
            items.value = data.items.map(orderItem => {
                // Tenta encontrar o produto no menu atual
                // Flattening: Categorias -> Produtos
                let currentProduct = null;
                
                // Varre categorias e subcategorias
                for (const cat of menuStore.categories) {
                    if (cat.products) {
                        const found = cat.products.find(p => p.name === orderItem.name); // Match por nome é mais seguro se IDs mudarem, mas ID é ideal
                        if (found) { currentProduct = found; break; }
                    }
                    if (cat.children) {
                        for (const child of cat.children) {
                            const foundChild = child.products.find(p => p.name === orderItem.name);
                            if (foundChild) { currentProduct = foundChild; break; }
                        }
                    }
                }

                // Estado Inicial
                const isAvailable = !!currentProduct && currentProduct.is_available;
                
                // Recalcula preço (Preço atual do produto + soma dos modificadores)
                // Nota: Modificadores complexos são difíceis de validar 100%. 
                // Assumimos o preço do modificador antigo, mas usamos o preço base atual.
                let currentPrice = 0;
                let priceChanged = false;

                if (currentProduct) {
                    let modsTotal = 0;
                    if (orderItem.modifiers) {
                        modsTotal = orderItem.modifiers.reduce((sum, m) => sum + parseFloat(m.price || 0), 0);
                    }
                    currentPrice = parseFloat(currentProduct.price) + modsTotal;
                    
                    // Verifica se mudou em relação ao histórico
                    const oldTotal = parseFloat(orderItem.line_total) / orderItem.qty;
                    if (Math.abs(currentPrice - oldTotal) > 0.1) priceChanged = true;
                }

                return {
                    ...orderItem,
                    currentPrice: currentProduct ? currentPrice : 0,
                    isAvailable: isAvailable,
                    selected: isAvailable, // Seleciona automaticamente se disponível
                    priceChanged: priceChanged,
                    productId: currentProduct ? currentProduct.id : null // ID atual para adicionar ao carrinho
                };
            });
        }
    } catch (e) {
        console.error(e);
        notify('error', 'Erro', 'Não foi possível carregar os itens.');
        isOpen.value = false;
    } finally {
        isLoading.value = false;
    }
};

const toggleSelection = (item) => {
    if (!item.isAvailable) return;
    item.selected = !item.selected;
};

const confirmReorder = () => {
    const toAdd = items.value.filter(i => i.selected && i.isAvailable);
    
    let addedCount = 0;
    toAdd.forEach(item => {
        // Monta o objeto compatível com cartStore.addItem
        const cartItem = {
            id: item.productId, // Usa o ID atual recuperado do menu
            name: item.name,
            price: parseFloat(item.currentPrice), // Usa o preço recalculado
            qty: item.qty,
            image: null, // Opcional
            modifiers: item.modifiers || [],
            uniqueId: Date.now() + Math.random() // Novo ID único
        };
        cartStore.items.push(cartItem);
        addedCount++;
    });

    if (addedCount > 0) {
        cartStore.isOpen = true; // Abre o carrinho
        notify('success', 'Adicionado', `${addedCount} itens enviados ao carrinho.`);
        isOpen.value = false;
    }
};

defineExpose({ open });
</script>

<style scoped>
.animate-slide-up { animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
</style>