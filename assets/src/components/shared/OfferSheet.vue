<template>
    <div v-if="cartStore.offerSheetOpen && cartStore.activeOffer" class="fixed inset-0 z-50 flex items-end justify-center pointer-events-none">
        
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm pointer-events-auto transition-opacity" @click="cartStore.rejectOffer()"></div>

        <div class="bg-surface-900 border-t border-surface-800 w-full max-w-md rounded-t-[2rem] p-6 pb-8 shadow-2xl relative pointer-events-auto transform transition-transform duration-300 ease-out animate-slide-up">
            
            <div class="w-12 h-1.5 bg-surface-700 rounded-full mx-auto mb-6"></div>

            <div class="text-center">
                <div class="inline-block bg-primary-500/10 text-primary-500 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide mb-4 border border-primary-500/20">
                    Oportunidade Única
                </div>

                <h3 class="text-xl font-bold text-white mb-2">
                    {{ cartStore.activeOffer.call_to_action }}
                </h3>

                <div class="my-6 relative">
                    <img 
                        :src="cartStore.activeOffer.image" 
                        class="w-32 h-32 object-cover rounded-2xl mx-auto shadow-lg border-4 border-surface-800"
                        alt="Produto da Oferta"
                    />
                    <div class="absolute -right-2 top-0 bg-red-600 text-white font-bold rounded-full w-12 h-12 flex items-center justify-center text-sm shadow-md rotate-12 border-2 border-surface-900">
                        OFF
                    </div>
                </div>

                <div class="flex items-center justify-center gap-3 mb-8">
                    <span class="text-surface-400 line-through text-lg font-bold">
                        {{ formatCurrency(cartStore.activeOffer.original_price) }}
                    </span>
                    <i class="fa-solid fa-arrow-right text-surface-500"></i>
                    <span class="text-3xl font-black text-primary-500">
                        {{ formatCurrency(cartStore.activeOffer.promo_price) }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <button 
                        @click="cartStore.rejectOffer()"
                        class="w-full py-3.5 rounded-xl font-bold text-surface-400 bg-surface-800 border border-surface-700 hover:bg-surface-700 transition-colors"
                    >
                        Não, obrigado
                    </button>
                    <button 
                        @click="cartStore.acceptOffer()"
                        class="w-full py-3.5 rounded-xl font-bold text-surface-900 bg-primary-500 hover:bg-primary-400 shadow-lg shadow-primary-900/20 transition-all hover:scale-[1.02]"
                    >
                        Eu Quero!
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useCartStore } from '@/stores/cart-store';
import { useFormat } from '@/composables/useFormat'; 

const cartStore = useCartStore();
const { formatCurrency } = useFormat(); 
</script>

<style scoped>
.animate-slide-up {
    animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}
</style>