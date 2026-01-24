<template>
<div class="grid grid-cols-1 xl:grid-cols-[1fr_auto] gap-4 w-full h-full">
        
    <div class="relative bg-surface-900 border border-surface-800 rounded-3xl shadow-xl overflow-hidden flex flex-col transition-all duration-300">
        
        <TableGrid @request-new-account="onRequestNewAccount" />

        <TableAccountsModal 
            v-if="targetTable"
            v-model:visible="showAccountsModal" 
            :session-id="targetTable.sessionId"
            :table-number="targetTable.number"
            @open-payment="handleOpenPayment"
        />
    </div>

    <div class="hidden xl:flex flex-col w-52 bg-surface-900 border border-surface-800 rounded-3xl shadow-xl overflow-hidden h-full">
        <ActivityFeed />
    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useSessionStore } from '@/stores/session-store';
import TableGrid from '@/components/manager/TableGrid.vue';
import TableAccountsModal from '@/components/pdv/TableAccountsModal.vue';
import ActivityFeed from '@/components/manager/ActivityFeed.vue'; 

const router = useRouter();
const sessionStore = useSessionStore();

const showAccountsModal = ref(false);
const targetTable = ref(null);

const onRequestNewAccount = (table) => {
    targetTable.value = table;
    
    // --- CORREÇÃO "MODO GARÇOM" ---
    // Injetamos o ID na store para que as funções globais (como createAccount) saibam onde estamos
    if (table && table.sessionId) {
        sessionStore.sessionId = table.sessionId;
        sessionStore.identifier = table.number;
        // Não definimos sessionType para evitar redirecionamentos automáticos indesejados
    }
    
    showAccountsModal.value = true;
};

const handleOpenPayment = async ({ accountName, amount }) => {
    if (!targetTable.value) return;

    await sessionStore.resumeSession(targetTable.value.sessionId, targetTable.value.number, false);
    
    if (accountName && accountName !== 'all') {
        sessionStore.setAccount(accountName);
    }

    router.push({ path: '/staff/pdv', query: { mode: 'checkout' } });
};
</script>