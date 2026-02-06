<template>
    <component 
        :is="isMobile ? Drawer : Dialog"
        :visible="visible" 
        @update:visible="emit('update:visible', $event)"
        :modal="true"
        :position="isMobile ? 'bottom' : 'center'"
        :class="isMobile ? '!h-auto !max-h-[85vh]' : ''"
        :style="!isMobile ? { width: '450px' } : undefined"
        :pt="ptOptions"
    >
        <template #header>
            <div class="flex flex-col w-full">
                <div v-if="isMobile" class="self-center w-12 h-1.5 bg-surface-3/50 rounded-full mb-6"></div>
                
                <div class="flex items-start justify-between">
                    <div class="flex flex-col">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-8 h-8 rounded-full bg-primary-500/10 flex items-center justify-center text-primary-500">
                                <i class="fa-solid fa-users-viewfinder"></i>
                            </span>
                            <span class="text-[10px] font-black uppercase tracking-widest text-primary-500">
                                <template v-if="tableNumber">Mesa {{ tableNumber }}</template>
                                <template v-else>Venda Balcão</template>
                            </span>
                        </div>
                        <h2 class="text-2xl font-black text-surface-0 leading-none">Quem vai pedir?</h2>
                        <p class="text-xs font-bold text-surface-400 mt-1">Selecione uma comanda ou crie nova</p>
                    </div>
                </div>
            </div>
        </template>
        
        <div class="flex flex-col bg-surface-1 overflow-hidden">

            <div class="flex-1 overflow-y-auto scrollbar-hidden p-6 space-y-3">
                
                <div 
                    v-for="acc in visibleAccounts" 
                    :key="acc"
                    class="group flex items-center justify-between p-4 rounded-[20px] cursor-pointer transition-all border border-surface-3/10 relative overflow-hidden"
                    :class="isActive(acc) ? 'bg-primary-500/10 border-primary-500 ring-1 ring-primary-500' : 'bg-surface-2 hover:bg-surface-3 hover:border-surface-3/50'"
                    @click="selectAccount(acc)"
                >
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-black shadow-sm transition-colors"
                             :class="isActive(acc) ? 'bg-primary-500 text-surface-950' : 'bg-surface-3 text-surface-400 group-hover:bg-surface-4 group-hover:text-surface-200'">
                            {{ getInitials(acc) }}
                        </div>
                        
                        <div class="flex flex-col">
                            <span class="font-bold text-base text-surface-0 leading-none">{{ acc }}</span>
                            <span v-if="isActive(acc)" class="text-[10px] font-black text-primary-400 uppercase tracking-wider mt-1">Selecionado</span>
                        </div>
                    </div>

                    <div v-if="isActive(acc)" class="w-8 h-8 rounded-full bg-primary-500 text-surface-950 flex items-center justify-center shadow-lg shadow-primary-500/20 animate-scale-in">
                        <i class="fa-solid fa-check text-sm"></i>
                    </div>
                    <i v-else class="fa-solid fa-chevron-right text-surface-500 opacity-0 group-hover:opacity-50 transition-opacity"></i>
                </div>

                <div v-if="visibleAccounts.length === 0" class="text-center py-6">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-surface-2 mb-2 opacity-50">
                        <i class="fa-solid fa-user-plus text-surface-400"></i>
                    </div>
                    <p class="text-xs font-bold text-surface-500 italic">
                        {{ enforceIdentification ? 'Crie um nome para continuar.' : 'Nenhuma conta extra criada.' }}
                    </p>
                </div>
            </div>

            <div class="shrink-0 p-6 pt-2 pb-8 bg-surface-1 border-t border-surface-3/10 md:pb-6">
                <label class="text-[10px] font-black text-surface-400 uppercase mb-2 block tracking-widest pl-1">
                    Adicionar Pessoa
                </label>
                <div class="flex gap-2 relative">
                    <InputText 
                        v-model="newAccountName" 
                        placeholder="Nome (ex: João)" 
                        class="!flex-1 !bg-surface-2 !border-none !text-surface-0 !h-14 !rounded-[20px] !pl-5 !pr-4 !text-base focus:!ring-2 focus:!ring-primary-500/50 shadow-sm"
                        @keydown.enter="handleCreateAccount"
                    />
                    
                    <Button 
                        icon="fa-solid fa-plus" 
                        class="!w-14 !h-14 !rounded-[20px] !border-none shadow-lg active:scale-95 transition-transform"
                        :class="newAccountName ? '!bg-primary-500 !text-surface-950 hover:!bg-primary-400' : '!bg-surface-3 !text-surface-500 cursor-not-allowed'"
                        :loading="isCreating"
                        @click="handleCreateAccount"
                        :disabled="!newAccountName"
                    />
                </div>
            </div>

        </div>
    </component>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import { useMobile } from '@/composables/useMobile';
import Dialog from 'primevue/dialog';
import Drawer from 'primevue/drawer';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

const props = defineProps(['visible', 'sessionId', 'tableNumber', 'enforceIdentification']);
const emit = defineEmits(['update:visible', 'account-selected']);

const sessionStore = useSessionStore();
const { isMobile } = useMobile();
const newAccountName = ref('');
const isCreating = ref(false);

const ptOptions = computed(() => {
    const common = {
        content: { class: '!bg-surface-1 !p-0 !h-auto' }, 
        mask: { class: '!bg-surface-base/80 backdrop-blur-sm' },
        closeButton: { class: '!hidden' }
    };
    if (isMobile.value) {
        return { ...common, root: { class: '!bg-surface-1 !border-t !border-surface-3/10 !rounded-t-[32px] !shadow-2xl !overflow-hidden !h-auto !max-h-[85vh]' }, header: { class: '!bg-surface-1 !border-b !border-surface-3/10 !px-6 !py-4 relative !z-20' } };
    } else {
        return { ...common, root: { class: '!bg-surface-1 !border-none !rounded-[28px] !shadow-2xl' }, header: { class: '!bg-surface-1 !border-b !border-surface-3/10 !px-6 !py-4' }, content: { class: '!bg-surface-1 !p-0 !rounded-b-[28px] !overflow-hidden' } };
    }
});

const visibleAccounts = computed(() => {
    const allAccounts = sessionStore.accounts || [];
    
    // Se estivermos forçando identificação (lançamento balcão), removemos 'Principal'
    if (props.enforceIdentification) {
        return allAccounts.filter(acc => acc !== 'Principal');
    }

    if (allAccounts.length > 1) {
        return allAccounts.filter(acc => acc !== 'Principal');
    }
    return allAccounts; 
});

const isActive = (acc) => sessionStore.currentAccount === acc;

const getInitials = (name) => {
    return name.substring(0, 2).toUpperCase();
};

const selectAccount = (accountName) => {
    emit('account-selected', accountName);
    emit('update:visible', false);
};

const handleCreateAccount = async () => {
    const name = newAccountName.value.trim();
    if (!name) return;
    
    isCreating.value = true;

    // CENÁRIO 1: Sessão já existe (Mesa ou Balcão já iniciado) -> Cria no Backend
    if (props.sessionId) {
        const success = await sessionStore.createAccount(name, false, props.sessionId);
        if (success) {
            newAccountName.value = '';
            selectAccount(name);
        }
    } 
    // CENÁRIO 2: Sessão nova (Balcão antes de enviar) -> Apenas seleciona localmente
    // O PosInterface vai usar esse nome para criar a sessão no "Lançar"
    else {
        // Simula delay para feedback visual
        await new Promise(r => setTimeout(r, 300));
        newAccountName.value = '';
        selectAccount(name);
    }
    
    isCreating.value = false;
};
</script>

<style scoped>
.scrollbar-hidden::-webkit-scrollbar { display: none; }
.scrollbar-hidden { -ms-overflow-style: none; scrollbar-width: none; }

.animate-scale-in { animation: scaleIn 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
@keyframes scaleIn { from { transform: scale(0); } to { transform: scale(1); } }
</style>