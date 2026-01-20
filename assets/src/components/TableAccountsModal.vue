<template>
  <Dialog 
    :visible="visible" 
    @update:visible="emit('update:visible', $event)"
    modal 
    :style="{ width: '400px' }"
    :pt="{
        root: { class: '!bg-surface-900 !border !border-surface-700 !rounded-2xl !shadow-2xl' },
        header: { class: '!bg-surface-950 !border-b !border-surface-800 !p-5' },
        content: { class: '!bg-surface-900 !p-0 !overflow-hidden' },
        closeButton: { class: '!text-surface-400 hover:!text-white' },
        mask: { class: '!bg-black/60 backdrop-blur-sm' }
    }"
  >
    <template #header>
        <div class="flex flex-col">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-users-viewfinder text-primary-500"></i> 
                Mesa {{ tableNumber }}
            </h2>
            <p class="text-surface-400 text-xs mt-1">Selecione quem vai pedir.</p>
        </div>
    </template>

    <div class="flex flex-col min-h-[250px] max-h-[70vh]">
        
        <div class="flex-1 overflow-y-auto p-4 space-y-3 scrollbar-thin">
            
            <div v-if="isLoading" class="flex justify-center py-10">
                <i class="fa-solid fa-spinner fa-spin text-2xl text-primary-500"></i>
            </div>

            <div v-else-if="!accounts || accounts.length === 0" class="flex flex-col items-center justify-center h-40 text-surface-500 border border-dashed border-surface-800 rounded-xl bg-surface-950/50">
                <i class="fa-solid fa-user-plus text-2xl mb-2 opacity-50"></i>
                <span class="text-sm">Nenhuma conta aberta.</span>
                <span class="text-xs opacity-70">Crie a primeira abaixo.</span>
            </div>

            <div v-else v-for="account in accounts" :key="account" 
                 class="group relative flex items-center justify-between p-3 rounded-xl border border-surface-700 bg-surface-800/40 hover:bg-surface-800 hover:border-primary-500/50 transition-all cursor-pointer"
                 :class="{'!border-primary-500 !bg-primary-500/10': currentAccount === account}"
                 @click="selectAccount(account)"
            >
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-colors shadow-inner"
                         :class="currentAccount === account ? 'bg-primary-500 text-surface-950' : 'bg-surface-700 text-primary-400 group-hover:bg-primary-500 group-hover:text-surface-950'">
                        {{ getInitials(account) }}
                    </div>
                    <div>
                        <div class="font-bold text-white text-base leading-none mb-1">{{ account }}</div>
                        <div class="text-[10px] text-surface-400 font-mono">
                            {{ getAccountItemCount(account) }} itens
                        </div>
                    </div>
                </div>

                <div class="text-right">
                    <div class="text-white font-mono font-bold text-sm">{{ formatMoney(getAccountTotal(account)) }}</div>
                    <div v-if="currentAccount === account" class="text-[9px] text-primary-400 font-bold uppercase tracking-wider mt-0.5">
                        Selecionada
                    </div>
                </div>
            </div>

        </div>

        <div class="p-4 bg-surface-950 border-t border-surface-800 shrink-0">
            
            <div v-if="!isCreating" class="animate-fade-in">
                <button 
                    class="w-full py-3 border border-dashed border-surface-600 rounded-xl text-surface-400 font-bold hover:border-primary-500 hover:text-primary-500 hover:bg-surface-800/50 transition-all flex items-center justify-center gap-2 text-sm"
                    @click="isCreating = true"
                >
                    <i class="fa-solid fa-plus"></i> {{ sessionId ? 'Nova Conta' : 'Abrir Mesa' }}
                </button>
            </div>

            <div v-else class="flex flex-col gap-3 animate-fade-in-up">
                <div class="flex gap-2">
                    <InputText 
                        v-model="newAccountName" 
                        placeholder="Nome (Ex: João) ou Nº" 
                        class="!bg-surface-900 !border-surface-700 !text-white flex-1 !h-12 !text-base"
                        autofocus
                        @keyup.enter="createAccount"
                    />
                    <Button 
                        icon="fa-solid fa-check" 
                        class="!bg-primary-500 hover:!bg-primary-400 !border-none !text-surface-950 !w-12 !h-12 !rounded-lg" 
                        @click="createAccount"
                        :loading="isSubmitting"
                    />
                </div>
                <div v-if="sessionId" class="flex items-center gap-2 px-1">
                    <Checkbox v-model="isPhysicalCommand" binary inputId="isCmd" />
                    <label for="isCmd" class="text-xs text-surface-400 cursor-pointer select-none">É comanda física (Cartão)</label>
                </div>
            </div>

            <div v-if="sessionId" class="mt-3 pt-3 border-t border-surface-800 flex justify-between items-center">
                <span class="text-surface-500 text-xs uppercase font-bold">Total Mesa</span>
                <span class="text-lg font-bold text-white font-mono">{{ formatMoney(totalSession) }}</span>
            </div>

        </div>
    </div>
  </Dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import { useRouter, useRoute } from 'vue-router';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';

const props = defineProps(['visible', 'sessionId', 'tableNumber']);
const emit = defineEmits(['update:visible', 'account-selected']);

const sessionStore = useSessionStore();
const router = useRouter();
const route = useRoute();

const isLoading = ref(false);
const isCreating = ref(false);
const isSubmitting = ref(false);
const newAccountName = ref('');
const isPhysicalCommand = ref(false);

const accounts = computed(() => sessionStore.accounts);
const currentAccount = computed(() => sessionStore.currentAccount);
const totalSession = computed(() => sessionStore.totals.total);

const formatMoney = (val) => 'R$ ' + (parseFloat(val) || 0).toFixed(2).replace('.', ',');
const getInitials = (name) => name ? name.substring(0, 2).toUpperCase() : '??';
const getAccountTotal = (name) => sessionStore.accountTotal(name);
const getAccountItemCount = (name) => (sessionStore.groupedItems[name] || []).length;

const loadData = async () => {
    // 1. Configura identificadores
    sessionStore.identifier = props.tableNumber;
    sessionStore.sessionType = 'table';
    sessionStore.sessionId = props.sessionId || null;

    // 2. Busca ou Limpa
    if (props.sessionId) {
        isLoading.value = true;
        await sessionStore.refreshSession();
        isLoading.value = false;
    } else {
        sessionStore.accounts = [];
        sessionStore.currentAccount = 'Principal';
        isCreating.value = true; // Abre input direto se for mesa nova
    }
};

watch(() => props.visible, (newVal) => {
    if (newVal) {
        isCreating.value = false; // Reset visual ao abrir
        loadData();
    }
});

const selectAccount = (account) => {
    sessionStore.setAccount(account);
    sessionStore.sessionType = 'table'; 
    sessionStore.identifier = props.tableNumber;
    sessionStore.sessionId = props.sessionId; 

    emit('account-selected', account);
    
    if (route.name !== 'pdv') router.push('/pdv');
    close();
};

const createAccount = async () => {
    if (!newAccountName.value.trim()) return;
    isSubmitting.value = true;
    
    if (props.sessionId) {
        const success = await sessionStore.createAccount(newAccountName.value, isPhysicalCommand.value);
        if (success) enterPos(newAccountName.value);
    } else {
        sessionStore.sessionId = null; 
        sessionStore.accounts = [newAccountName.value];
        enterPos(newAccountName.value);
    }
    
    isSubmitting.value = false;
};

const enterPos = (accountName) => {
    sessionStore.setAccount(accountName);
    sessionStore.sessionType = 'table';
    sessionStore.identifier = props.tableNumber;
    newAccountName.value = '';
    isCreating.value = false;
    if (route.name !== 'pdv') router.push('/pdv');
    close();
};

const close = () => {
    emit('update:visible', false);
    isCreating.value = false;
};
</script>

<style scoped>
.animate-fade-in-up { animation: fadeInUp 0.3s ease-out; }
@keyframes fadeInUp { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
</style>