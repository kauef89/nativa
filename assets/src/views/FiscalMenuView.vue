<template>
  <div class="flex flex-col h-full w-full bg-surface-1 rounded-[24px] overflow-hidden">
    
    <div class="shrink-0 h-20 px-6 flex items-center justify-between border-b border-surface-3/10">
        <div>
            <h1 class="text-2xl font-black text-surface-0 flex items-center gap-3">
                <i class="fa-solid fa-file-invoice text-primary-500"></i> Menu Fiscal (PAF)
            </h1>
            <p class="text-surface-400 text-xs font-bold">Obrigações Acessórias - Ato DIAT 032/2023</p>
        </div>
        
        <div class="flex gap-3">
            <Button 
                label="Gerar Arquivo I (Itens)" 
                icon="fa-solid fa-file-code" 
                class="!bg-surface-2 hover:!bg-surface-3 !text-surface-0 !border-none !font-black !rounded-full"
                :loading="generating"
                @click="generateFileI"
            />
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-6">
        
        <div v-if="loading" class="flex justify-center p-20">
            <i class="fa-solid fa-circle-notch fa-spin text-4xl text-primary-500"></i>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            
            <div v-for="file in files" :key="file.name" class="bg-surface-2 p-4 rounded-[20px] border border-surface-3/10 flex flex-col gap-3 group hover:border-primary-500/30 transition-all">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-surface-1 flex items-center justify-center text-primary-500 text-lg">
                        <i class="fa-regular fa-file-lines"></i>
                    </div>
                    <div>
                        <div class="font-bold text-surface-0 text-sm truncate w-48">{{ file.name }}</div>
                        <div class="text-[10px] text-surface-400 font-bold uppercase">{{ file.type }} • {{ file.date }}</div>
                    </div>
                </div>
                
                <div class="flex gap-2 mt-auto">
                    <a :href="file.url" target="_blank" class="flex-1 text-center bg-primary-500/10 hover:bg-primary-500 hover:text-surface-950 text-primary-400 py-2 rounded-lg text-xs font-black uppercase transition-colors">
                        <i class="fa-solid fa-download mr-1"></i> Baixar TXT
                    </a>
                    <button class="flex-1 bg-surface-1 hover:bg-surface-3 text-surface-300 hover:text-surface-0 py-2 rounded-lg text-xs font-black uppercase transition-colors">
                        <i class="fa-solid fa-print mr-1"></i> Imprimir
                    </button>
                </div>
            </div>

            <div v-if="files.length === 0" class="col-span-full text-center py-10 text-surface-500 opacity-60">
                <i class="fa-solid fa-folder-open text-4xl mb-3"></i>
                <p class="font-bold">Nenhum arquivo fiscal gerado.</p>
            </div>

        </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import { notify } from '@/services/notify';
import Button from 'primevue/button';

const files = ref([]);
const loading = ref(false);
const generating = ref(false);

const fetchFiles = async () => {
    loading.value = true;
    try {
        const { data } = await api.get('/fiscal/menu');
        if(data.success) {
            files.value = data.files;
        }
    } catch(e) {
        console.error(e);
        notify('error', 'Erro', 'Falha ao carregar arquivos fiscais.');
    } finally {
        loading.value = false;
    }
};

const generateFileI = async () => {
    generating.value = true;
    try {
        const { data } = await api.post('/fiscal/generate-file-i');
        if(data.success) {
            notify('success', 'Gerado', data.message);
            await fetchFiles();
        }
    } catch(e) {
        notify('error', 'Erro', 'Falha ao gerar arquivo.');
    } finally {
        generating.value = false;
    }
};

onMounted(fetchFiles);
</script>