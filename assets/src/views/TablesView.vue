<template>
  <div class="grid grid-cols-1 xl:grid-cols-3 gap-4 w-full h-full">
        
    <div class="relative bg-surface-1 rounded-[24px] overflow-hidden flex flex-col transition-all duration-300 xl:col-span-2">
        
        <TableGrid @request-new-account="onRequestNewAccount" />

        <TableAccountsModal 
            v-if="targetTable"
            v-model:visible="showAccountsModal" 
            :session-id="targetTable.sessionId"
            :table-number="targetTable.number"
            @account-selected="handleAccountSelection"
        />
    </div>

    <div class="hidden xl:flex flex-col bg-surface-1 rounded-[24px] overflow-hidden h-full xl:col-span-1">
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
    if (table && table.sessionId) {
        // Prepara a store mas não redireciona ainda
        sessionStore.sessionId = table.sessionId;
        sessionStore.identifier = table.number;
        sessionStore.sessionType = 'table'; // Garante o tipo
    }
    showAccountsModal.value = true;
};

// NOVA FUNÇÃO: Gerencia o clique no modal
const handleAccountSelection = (accountName) => {
    if (!targetTable.value) return;

    // 1. Configura a sessão
    sessionStore.resumeSession(targetTable.value.sessionId, targetTable.value.number, false);
    
    // 2. Define a sub-conta (Ex: "João")
    sessionStore.setAccount(accountName);

    // 3. Vai para o PDV
    router.push('/staff/pdv');
};
</script>