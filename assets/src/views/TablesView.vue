<template>
  <div class="w-full h-full">
    
    <pos-interface 
        v-if="sessionStore.hasOpenSession && sessionStore.sessionType === 'table'" 
        @exit="handleExitSession" 
    />
    
    <div v-else class="w-full h-full flex items-center justify-center bg-surface-950">
        <table-grid />
    </div>

  </div>
</template>

<script setup>
import { useSessionStore } from '@/stores/session-store';
import { useTablesStore } from '@/stores/tables-store';
import TableGrid from '@/components/TableGrid.vue';
import PosInterface from '@/components/PosInterface.vue';

const sessionStore = useSessionStore();
const tablesStore = useTablesStore();

const handleExitSession = () => {
    sessionStore.leaveSession();
    tablesStore.fetchTables();
};
</script>