<template>
  <div class="p-8 w-full h-full flex flex-col bg-surface-950 overflow-y-auto">
    
    <div class="text-3xl font-bold text-white mb-8 flex items-center">
      <i class="pi pi-truck mr-3 text-primary-500 text-2xl"></i> Gestão de Delivery
    </div>

    <div class="max-w-4xl w-full mx-auto">
        
        <div class="bg-surface-900 p-8 rounded-2xl border border-surface-800 shadow-xl mb-8">
            <div class="text-xl font-bold text-white mb-6 border-b border-surface-800 pb-4">
                Novo Pedido (Simulação)
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex flex-col gap-2">
                    <label class="font-bold text-surface-400 text-sm">Nome do Cliente</label>
                    <InputText v-model="customerName" placeholder="Ex: Ana Silva" class="!bg-surface-950 !border-surface-700" />
                </div>
                <div class="flex flex-col gap-2">
                    <label class="font-bold text-surface-400 text-sm">Telefone</label>
                    <InputText v-model="phone" placeholder="(00) 00000-0000" class="!bg-surface-950 !border-surface-700" />
                </div>
                <div class="flex flex-col gap-2 col-span-1 md:col-span-2">
                    <label class="font-bold text-surface-400 text-sm">Endereço de Entrega</label>
                    <InputText v-model="address" placeholder="Rua, Número, Bairro" class="!bg-surface-950 !border-surface-700" />
                </div>
            </div>
            
            <div class="mt-6 p-4 bg-primary-500/10 rounded-lg border border-primary-500/20 text-primary-300 text-sm flex items-start">
                <i class="pi pi-info-circle mr-2 mt-0.5"></i>
                <div>
                    <b>Teste do Keep-Alive:</b> Preencha este formulário, navegue para "Mesas" e volte. 
                    Seus dados devem permanecer aqui, provando que a arquitetura SPA está funcionando.
                </div>
            </div>
        </div>

        <div class="text-xl font-bold text-white mb-4">Pedidos em Andamento</div>
        
        <div class="bg-surface-900 border-2 border-dashed border-surface-800 rounded-2xl p-12 flex flex-col items-center justify-center text-surface-500">
            <i class="pi pi-inbox text-5xl mb-4 opacity-30"></i>
            <span class="font-medium">Nenhum pedido de delivery ativo no momento.</span>
        </div>

    </div>

  </div>
</template>

<script setup>
import { ref } from 'vue';
// InputText é auto-importado

const customerName = ref('');
const address = ref('');
const phone = ref('');
</script>