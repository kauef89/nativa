<template>
  <div class="flex flex-col items-center w-full p-8 h-full overflow-y-auto">
    
    <div class="text-3xl font-bold text-white mb-10 flex items-center tracking-tight">
      <i class="pi pi-map mr-3 text-primary-500 text-2xl"></i> Mapa de Mesas
    </div>

    <div v-if="store.isLoading" class="p-10">
      <ProgressSpinner />
    </div>

    <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-6 w-full max-w-7xl">
      
      <div 
        v-for="table in store.tables" 
        :key="table.number" 
      >
        <div 
            class="group relative bg-surface-900 border border-surface-800 rounded-2xl p-6 cursor-pointer transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl hover:border-primary-500/30 flex flex-col items-center justify-center h-40 overflow-hidden"
            @click="handleTableClick(table)"
        >
            <div 
                class="absolute top-4 right-4 w-3 h-3 rounded-full shadow-[0_0_10px_rgba(0,0,0,0.5)]"
                :class="table.status === 'occupied' ? 'bg-red-500 shadow-red-500/50' : 'bg-emerald-500 shadow-emerald-500/50'"
            ></div>

            <i class="text-4xl mb-4 transition-transform duration-300 group-hover:scale-110" 
               :class="table.status === 'occupied' ? 'pi pi-users text-surface-600' : 'pi pi-stop text-surface-700 group-hover:text-primary-500'"></i>
            
            <span class="text-2xl font-bold text-surface-200 group-hover:text-white">
                {{ table.number }}
            </span>
            
            <span class="text-xs uppercase font-bold mt-2 tracking-widest" 
                  :class="table.status === 'occupied' ? 'text-red-500' : 'text-emerald-500'">
                {{ table.status === 'occupied' ? 'OCUPADA' : 'LIVRE' }}
            </span>

            <div class="absolute inset-0 bg-gradient-to-tr from-primary-500/0 to-primary-500/0 group-hover:from-primary-500/5 group-hover:to-transparent transition-all duration-500 pointer-events-none"></div>
        </div>
      </div>

    </div>

    <Button 
        icon="pi pi-refresh" 
        label="Atualizar Status" 
        text 
        class="mt-12 text-surface-400 hover:text-white" 
        @click="store.fetchTables"
    />

  </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useTablesStore } from '@/stores/tables-store';
import { useSessionStore } from '@/stores/session-store';

const store = useTablesStore();
const sessionStore = useSessionStore();

const handleTableClick = async (table) => {
  if (table.status === 'occupied') {
    await sessionStore.resumeSession(table.sessionId, table.number);
  } else {
    await sessionStore.openSession('table', table.number);
    store.fetchTables();
  }
};

onMounted(() => {
  store.fetchTables();
});
</script>