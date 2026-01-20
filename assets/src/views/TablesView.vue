<template>
  <div class="grid grid-cols-1 xl:grid-cols-[1fr_360px] gap-4 w-full h-full">
    
    <div class="relative bg-surface-900 border border-surface-800 rounded-3xl shadow-xl overflow-hidden flex flex-col transition-all duration-300">
        
        <TableGrid @request-new-account="onRequestNewAccount" />

        <TableAccountsModal 
            v-if="targetTable"
            v-model:visible="showAccountsModal" 
            :session-id="targetTable.sessionId"
            :table-number="targetTable.number"
        />
    </div>

    <div class="hidden xl:flex flex-col bg-surface-900 border border-surface-800 rounded-3xl shadow-xl overflow-hidden h-full">
        <ActivityFeed />
    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue';
import TableGrid from '@/components/TableGrid.vue';
import TableAccountsModal from '@/components/TableAccountsModal.vue';
import ActivityFeed from '@/components/ActivityFeed.vue'; 

const showAccountsModal = ref(false);
const targetTable = ref(null);

const onRequestNewAccount = (table) => {
    targetTable.value = table;
    showAccountsModal.value = true;
};
</script>