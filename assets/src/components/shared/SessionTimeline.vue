<template>
  <div class="flex flex-col h-full bg-surface-900 border-r border-surface-800">
    
    <div class="p-4 border-b border-surface-800 bg-surface-950 flex justify-between items-center shrink-0">
      <h3 class="font-black text-white uppercase tracking-widest text-sm flex items-center gap-2">
        <i class="fa-solid fa-clock-rotate-left text-primary-500"></i> Histórico
      </h3>
      <Button 
        label="Novo Pedido" 
        icon="fa-solid fa-plus" 
        size="small" 
        class="!bg-primary-600 hover:!bg-primary-500 !border-none !text-xs !font-black !px-3 !py-2"
        @click="$emit('close')"
      />
    </div>

    <div class="flex-1 overflow-y-auto p-4 space-y-6">
      
      <div v-if="!events || events.length === 0" class="flex flex-col items-center justify-center h-64 text-surface-500 opacity-60">
        <i class="fa-solid fa-timeline text-4xl mb-3"></i>
        <p class="text-sm font-medium">Nenhum evento registrado nesta sessão.</p>
      </div>

      <div v-else class="relative border-l-2 border-surface-700 ml-3 space-y-8 pb-4 pt-2">
        
        <div v-for="event in events" :key="event.id" class="relative pl-6 group">
          
          <div class="absolute -left-[9px] top-1 w-4 h-4 rounded-full border-2 border-surface-900 shadow-sm transition-transform group-hover:scale-125 z-10"
               :class="getEventColor(event.type)"></div>
          
          <div class="flex flex-col gap-1.5">
            <div class="flex items-center gap-2">
                <span class="text-[10px] font-bold text-surface-400 bg-surface-950 px-1.5 py-0.5 rounded border border-surface-800">
                    {{ formatDate(event.created_at, true) }}
                </span>
                <span class="text-xs font-bold text-white truncate">
                    {{ event.author_name || 'Sistema' }}
                </span>
            </div>
            
            <div class="text-sm text-surface-300 leading-snug font-medium">
              {{ event.description }}
            </div>

            <div v-if="event.type === 'swap'" class="bg-surface-800/50 p-3 rounded-lg border border-surface-700 mt-1 text-xs relative overflow-hidden">
                <div class="flex items-center gap-2 text-red-400 opacity-80 mb-1">
                    <i class="fa-solid fa-arrow-down text-[10px]"></i>
                    <span class="line-through font-medium">{{ event.meta?.old_item || 'Item anterior' }}</span>
                </div>
                <div class="flex items-center gap-2 text-green-400 font-bold">
                    <i class="fa-solid fa-arrow-up text-[10px]"></i>
                    <span>{{ event.meta?.new_item || 'Novo item' }}</span>
                </div>
                
                <div class="mt-2 pt-2 border-t border-surface-700/50 flex justify-between items-center">
                    <span class="text-[10px] uppercase font-black tracking-wider" :class="getStatusColor(event.status)">
                        {{ getStatusLabel(event.status) }}
                    </span>
                    <span v-if="event.approver_name" class="text-[9px] text-surface-500 flex items-center gap-1 font-bold">
                        <i class="fa-solid fa-user-shield"></i> {{ event.approver_name }}
                    </span>
                </div>
            </div>

            <div v-if="event.type === 'cancel' && event.status === 'pending'" class="bg-orange-500/10 p-2 rounded border border-orange-500/20 mt-1">
                <p class="text-xs text-orange-300 flex items-center gap-2 font-bold">
                    <i class="fa-solid fa-hourglass-half"></i> Aguardando aprovação
                </p>
            </div>

          </div>
        </div>

      </div>
    </div>
  </div>
</template>

<script setup>
import { useFormat } from '@/composables/useFormat'; 
import Button from 'primevue/button';

const props = defineProps({
    events: { type: Array, default: () => [] }
});

defineEmits(['close']);

const { formatDate } = useFormat(); 

const getEventColor = (type) => {
    switch(type) {
        case 'order': return 'bg-blue-500 shadow-[0_0_10px_rgba(59,130,246,0.4)]';
        case 'cancel': return 'bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.4)]';
        case 'swap': return 'bg-orange-500 shadow-[0_0_10px_rgba(249,115,22,0.4)]';
        case 'payment': return 'bg-green-500 shadow-[0_0_10px_rgba(34,197,94,0.4)]';
        default: return 'bg-surface-500';
    }
};

const getStatusLabel = (status) => {
    const map = { 'pending': 'Pendente', 'approved': 'Aprovado', 'rejected': 'Recusado' };
    return map[status] || status;
};

const getStatusColor = (status) => {
    if (status === 'approved') return 'text-green-400';
    if (status === 'rejected') return 'text-red-400';
    return 'text-orange-400 animate-pulse';
};
</script>