<template>
    <Dialog 
        :visible="visible" 
        @update:visible="emit('update:visible', $event)"
        modal 
        :style="{ width: '500px' }"
        :pt="{
            root: { class: '!bg-surface-900 !border !border-surface-800 !rounded-2xl' },
            header: { class: '!bg-surface-950 !border-b !border-surface-800 !py-4' },
            content: { class: '!bg-surface-900 !p-0' },
            footer: { class: '!bg-surface-950 !border-t !border-surface-800 !py-4 !px-6' }
        }"
    >
        <template #header>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-primary-500/20 flex items-center justify-center text-primary-500 border border-primary-500/30">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-white leading-none">Contas da Mesa {{ tableNumber }}</h2>
                    <p class="text-xs text-surface-400 mt-1">Selecione ou crie uma conta</p>
                </div>
            </div>
        </template>

        <div class="p-6 bg-surface-900">
            
            <div class="space-y-2 mb-6 max-h-[300px] overflow-y-auto scrollbar-thin">
                <div 
                    v-for="acc in visibleAccounts" 
                    :key="acc"
                    class="flex items-center justify-between p-3 rounded-xl border cursor-pointer transition-all hover:bg-surface-800"
                    :class="sessionStore.currentAccount === acc 
                        ? 'bg-primary-500/10 border-primary-500' 
                        : 'bg-surface-950 border-surface-800'"
                    @click="selectAccount(acc)"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold"
                             :class="sessionStore.currentAccount === acc ? 'bg-primary-500 text-surface-900' : 'bg-surface-800 text-surface-400'">
                            {{ acc.substring(0,2).toUpperCase() }}
                        </div>
                        <span class="font-bold text-sm text-white">{{ acc }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span v-if="sessionStore.currentAccount === acc" class="text-[10px] uppercase font-bold text-primary-400 tracking-wider">Ativa</span>
                        <i v-if="sessionStore.currentAccount === acc" class="fa-solid fa-check text-primary-500"></i>
                    </div>
                </div>
                <div v-if="visibleAccounts.length === 0" class="text-center py-4 text-surface-500 text-xs italic border-2 border-dashed border-surface-800 rounded-xl">
                    Nenhuma conta personalizada.
                </div>
            </div>

            <div class="pt-4 border-t border-surface-800">
                <label class="text-xs font-bold text-surface-500 uppercase mb-2 block">Nova Conta / Comanda</label>
                <div class="flex gap-2">
                    <InputText 
                        v-model="newAccountName" 
                        placeholder="Nome (ex: João)" 
                        class="!bg-surface-950 !border-surface-700 !text-white !flex-1 focus:!border-primary-500"
                        @keydown.enter="handleCreateAccount"
                    />
                    <Button 
                        icon="fa-solid fa-plus" 
                        class="!bg-surface-800 !border-surface-700 !text-white hover:!bg-surface-700"
                        :loading="isCreating"
                        @click="handleCreateAccount"
                    />
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end w-full">
                <Button label="Fechar" text class="!text-surface-400 hover:!text-white" @click="emit('update:visible', false)" />
            </div>
        </template>
    </Dialog>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';

const props = defineProps(['visible', 'sessionId', 'tableNumber']);
const emit = defineEmits(['update:visible', 'account-selected']);

const sessionStore = useSessionStore();
const newAccountName = ref('');
const isCreating = ref(false);

const visibleAccounts = computed(() => {
    const allAccounts = sessionStore.accounts || [];
    if (allAccounts.length > 1) {
        return allAccounts.filter(acc => acc !== 'Principal');
    }
    return allAccounts; 
});

// --- BLINDAGEM DE ID ---
const resolvedSessionId = computed(() => {
    // Tenta usar o ID passado via prop (Manager view)
    if (props.sessionId) return props.sessionId;
    // Se falhar, usa o ID da Store global (Garçom/PDV ou Manager corrigido)
    return sessionStore.sessionId;
});

const selectAccount = (accountName) => {
    emit('account-selected', accountName);
    emit('update:visible', false);
};

const handleCreateAccount = async () => {
    if (!newAccountName.value.trim()) return;
    
    // VERIFICAÇÃO ATUALIZADA
    // Aceita se tiver ID OU se tiver número da mesa (para criação lazy)
    if (!props.sessionId && !props.tableNumber) {
        console.error('ERRO: Nem ID de sessão nem número da mesa disponíveis.');
        alert('Erro: Dados da mesa insuficientes. Atualize a página.');
        return;
    }

    isCreating.value = true;
    const nameToCreate = newAccountName.value.trim();
    
    // Passamos o ID (pode ser null) e o Store vai lidar com o resto
    const success = await sessionStore.createAccount(nameToCreate, false, props.sessionId);
    
    if (success) {
        newAccountName.value = '';
        selectAccount(nameToCreate);
    }
    isCreating.value = false;
};
</script>