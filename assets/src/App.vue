<template>
  <div class="flex h-screen w-full overflow-hidden bg-surface-950 text-surface-200 font-sans p-3 gap-3">
    
    <Toast position="top-right" />

    <div 
        class="flex flex-col bg-surface-900 border border-surface-800 rounded-2xl shadow-2xl transition-all duration-300 ease-in-out shrink-0"
        :class="mini ? 'w-20' : 'w-max'"
    >
        <div class="flex items-center justify-between px-4 h-20 border-b border-surface-800 shrink-0 gap-4">
            <div v-if="!mini" class="flex items-center gap-3 overflow-hidden whitespace-nowrap animate-fade-in pr-2">
                <i class="pi pi-stop-circle text-primary-500 text-3xl"></i>
                <span class="text-xl font-bold text-white tracking-wide">Nativa</span>
            </div>

            <div v-else class="w-full flex justify-center">
                <i class="pi pi-stop-circle text-primary-500 text-3xl cursor-pointer" @click="mini = false" v-tooltip.right="'Expandir'"></i>
            </div>

            <Button 
                v-if="!mini"
                icon="pi pi-angle-left" 
                text rounded 
                class="!text-surface-300 hover:!text-white !w-8 !h-8"
                @click="mini = !mini"
            />
        </div>

        <div class="flex-1 overflow-y-auto py-4 px-2 space-y-2 scrollbar-thin">
            <router-link to="/tables" custom v-slot="{ navigate, isActive }">
                <div @click="navigate" 
                     class="flex items-center cursor-pointer p-3 rounded-xl transition-all duration-200 group relative overflow-hidden whitespace-nowrap"
                     :class="isActive ? 'bg-primary-500 text-surface-900 shadow-lg shadow-primary-500/20 font-bold' : 'text-surface-300 hover:bg-surface-800 hover:text-white'"
                     :title="mini ? 'Mesas' : ''"
                >
                    <i class="pi pi-table text-xl shrink-0" :class="mini ? 'mx-auto' : 'mr-3'"></i>
                    <span v-if="!mini" class="animate-fade-in">Mesas</span>
                </div>
            </router-link>

            <router-link to="/counter" custom v-slot="{ navigate, isActive }">
                <div @click="navigate" 
                     class="flex items-center cursor-pointer p-3 rounded-xl transition-all duration-200 group relative overflow-hidden whitespace-nowrap"
                     :class="isActive ? 'bg-primary-500 text-surface-900 shadow-lg shadow-primary-500/20 font-bold' : 'text-surface-300 hover:bg-surface-800 hover:text-white'"
                     :title="mini ? 'Balcão' : ''"
                >
                    <i class="pi pi-shopping-bag text-xl shrink-0" :class="mini ? 'mx-auto' : 'mr-3'"></i>
                    <span v-if="!mini" class="animate-fade-in">Balcão</span>
                </div>
            </router-link>

            <router-link to="/delivery" custom v-slot="{ navigate, isActive }">
                <div @click="navigate" 
                     class="flex items-center cursor-pointer p-3 rounded-xl transition-all duration-200 group relative overflow-hidden whitespace-nowrap"
                     :class="isActive ? 'bg-primary-500 text-surface-900 shadow-lg shadow-primary-500/20 font-bold' : 'text-surface-300 hover:bg-surface-800 hover:text-white'"
                     :title="mini ? 'Delivery' : ''"
                >
                    <i class="pi pi-truck text-xl shrink-0" :class="mini ? 'mx-auto' : 'mr-3'"></i>
                    <span v-if="!mini" class="animate-fade-in">Delivery</span>
                </div>
            </router-link>
        </div>

        <div class="p-2 border-t border-surface-800 shrink-0 space-y-2">
            
            <router-link to="/customers" custom v-slot="{ navigate, isActive }">
                <div @click="navigate" 
                     class="flex items-center cursor-pointer p-3 rounded-xl transition-all duration-200 group relative overflow-hidden whitespace-nowrap"
                     :class="isActive ? 'bg-surface-800 text-white font-bold' : 'text-surface-300 hover:bg-surface-800 hover:text-white'"
                     :title="mini ? 'Clientes' : ''"
                >
                    <i class="pi pi-users text-xl shrink-0" :class="mini ? 'mx-auto' : 'mr-3'"></i>
                    <span v-if="!mini" class="animate-fade-in">Clientes</span>
                </div>
            </router-link>

            <div class="flex items-center cursor-pointer p-3 rounded-xl text-surface-300 hover:bg-surface-800 hover:text-white transition-colors overflow-hidden whitespace-nowrap" :title="mini ? 'Produtos' : ''">
                <i class="pi pi-box text-xl shrink-0" :class="mini ? 'mx-auto' : 'mr-3'"></i>
                <span v-if="!mini" class="animate-fade-in">Produtos</span>
            </div>
        </div>
    </div>

    <div class="flex-1 flex flex-col h-full relative overflow-hidden">
        <router-view v-slot="{ Component }">
            <keep-alive>
                <component :is="Component" />
            </keep-alive>
        </router-view>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useToast } from 'primevue/usetoast';
import { registerToast } from '@/services/notify';

const mini = ref(false);
const toast = useToast();

onMounted(() => {
    registerToast(toast);
});
</script>

<style>
.animate-fade-in { animation: fadeIn 0.3s ease-in-out; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>