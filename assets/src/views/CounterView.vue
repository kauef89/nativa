<template>
  <div class="w-full h-full relative bg-surface-950">
    
    <div v-if="isInitializing" class="absolute inset-0 z-50 flex flex-col items-center justify-center bg-surface-950 text-surface-200">
       <ProgressSpinner style="width: 50px; height: 50px" />
       <div class="mt-4 text-lg font-medium text-surface-400 animate-pulse">
           Iniciando Venda Balcão...
       </div>
    </div>

    <pos-interface 
        v-else-if="sessionStore.hasOpenSession" 
        @exit="handleExitSession" 
    />

    <div v-else class="flex flex-col items-center justify-center h-full text-surface-500">
        <i class="pi pi-exclamation-triangle text-4xl mb-2 text-yellow-600"></i>
        <span>Erro ao iniciar sessão do balcão.</span>
        <Button label="Tentar Novamente" text class="mt-2" @click="initCounterSession" />
    </div>

  </div>
</template>

<script setup>
import { ref, onActivated, onMounted } from 'vue';
import { useSessionStore } from '@/stores/session-store';
import PosInterface from '@/components/PosInterface.vue';
// ProgressSpinner e Button são auto-importados

const sessionStore = useSessionStore();
const isInitializing = ref(false);

const initCounterSession = async () => {
    // Se já tem sessão aberta e é do tipo counter, não faz nada
    if (sessionStore.hasOpenSession && sessionStore.sessionType === 'counter') return;
    
    // Se tem sessão de MESA aberta, precisamos fechar antes (ou avisar)
    // Aqui assumimos que ao entrar no balcão, queremos uma venda balcão.
    
    isInitializing.value = true;
    // Abre sessão balcão anônima
    await sessionStore.openSession('counter', null, '');
    isInitializing.value = false;
};

// Tenta iniciar tanto ao montar quanto ao reativar (Keep-Alive)
onMounted(initCounterSession);
onActivated(initCounterSession);

const handleExitSession = () => {
    // No balcão, "Sair" pode significar apenas limpar a tela ou voltar para home
    // Como Balcão é uma rota principal, geralmente só limpamos os dados
    sessionStore.leaveSession();
    // Reinicia imediatamente uma nova sessão limpa
    initCounterSession();
};
</script>