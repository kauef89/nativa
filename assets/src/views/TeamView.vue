<template>
  <div class="flex flex-col h-full w-full overflow-hidden bg-surface-1 rounded-[24px] transition-all">
    
    <div class="shrink-0 h-20 px-6 flex items-center justify-between z-20 border-b border-surface-3/10 bg-surface-1">
        <div>
            <h1 class="text-xl md:text-2xl font-black text-surface-0 flex items-center gap-3">
                <i class="fa-solid fa-id-badge text-primary-500"></i> Gestão de Equipe
            </h1>
            <p class="text-surface-400 text-xs font-bold hidden md:block">Gerencie membros e permissões.</p>
        </div>

        <Button 
            v-if="isMobile && activeTab === 'members'"
            icon="fa-solid fa-plus" 
            rounded
            class="!w-10 !h-10 !bg-primary-500 !text-surface-950 !border-none !shadow-lg shrink-0 md:hidden"
            @click="openModal()" 
        />
    </div>

    <div class="px-4 pb-2 pt-2 bg-surface-1 shrink-0 z-10 flex justify-center border-b border-surface-3/10">
        <div class="bg-surface-2 p-1.5 rounded-full flex relative shadow-inner border border-surface-3/10 w-fit">
            
            <div 
                class="absolute top-1.5 w-10 h-10 bg-primary-500 rounded-full shadow-lg shadow-primary-500/20 transition-all duration-300 ease-[cubic-bezier(0.2,0,0,1)] z-0"
                :style="tabIndicatorStyle"
            ></div>

            <button 
                v-for="tab in tabs" :key="tab.id"
                class="w-14 h-10 flex items-center justify-center relative z-10 transition-colors select-none group rounded-full"
                :class="activeTab === tab.id ? 'text-surface-950' : 'text-surface-400 hover:text-surface-200'"
                @click="activeTab = tab.id"
                v-tooltip.bottom="tab.label"
            >
                <div class="relative flex items-center justify-center w-full h-full">
                    <i :class="[tab.icon, activeTab === tab.id ? 'scale-110' : '']" class="text-lg transition-transform duration-300"></i>
                </div>
            </button>
        </div>
    </div>

    <div class="flex-1 overflow-hidden flex flex-col relative bg-surface-1">
        
        <div v-if="activeTab === 'members'" class="h-full flex flex-col p-4 md:p-6 overflow-hidden animate-fade-in">
            
            <div class="hidden md:flex justify-end mb-6 shrink-0">
                <Button label="Novo Membro" icon="fa-solid fa-plus" class="!bg-primary-500 hover:!bg-primary-400 !border-none !font-black !rounded-full !text-surface-950 shadow-lg" @click="openModal()" />
            </div>

            <div class="flex-1 overflow-y-auto scrollbar-thin">
                <div v-if="store.isLoading" class="flex justify-center p-20">
                    <i class="fa-solid fa-spinner fa-spin text-4xl text-primary-500"></i>
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-5 pb-6">
                    
                    <div v-for="member in store.members" :key="member.id" 
                            class="bg-surface-2 p-5 rounded-[24px] flex flex-col gap-4 group hover:bg-surface-3 transition-all duration-300 shadow-sm relative overflow-hidden md:hover:-translate-y-1 cursor-default border border-transparent hover:border-surface-3/50"
                    >
                        <div class="absolute top-0 right-0 w-32 h-32 bg-primary-500/5 rounded-bl-[4rem] pointer-events-none transition-colors group-hover:bg-primary-500/10"></div>

                        <div class="flex justify-between items-start relative z-10">
                            <div class="flex items-center gap-4">
                                <div class="w-14 h-14 rounded-[18px] bg-surface-1 flex items-center justify-center text-xl font-black text-surface-400 border-2 border-surface-3/20 overflow-hidden shrink-0 shadow-inner">
                                    <img v-if="member.avatar" :src="member.avatar" class="w-full h-full object-cover">
                                    <span v-else>{{ member.name.charAt(0).toUpperCase() }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="font-black text-surface-0 text-lg truncate pr-2 leading-tight" :title="member.name">
                                        {{ abbreviateName(member.name) }}
                                    </div>
                                    <div class="text-xs text-surface-400 truncate mt-0.5 font-bold">{{ member.email }}</div>
                                </div>
                            </div>
                            <Button icon="fa-solid fa-pen" text rounded class="!text-surface-500 hover:!text-surface-0 !w-8 !h-8 !bg-surface-1 hover:!bg-surface-3" @click="openModal(member)" />
                        </div>

                        <div class="bg-surface-1 rounded-[16px] p-3 border border-surface-3/10 relative z-10 min-h-[70px]">
                            <div class="text-[10px] uppercase font-black text-surface-500 mb-2 tracking-wider">Acessos</div>
                            <div class="flex flex-wrap gap-2">
                                <span v-for="role in member.roles" :key="role" 
                                        class="text-[10px] font-black px-2.5 py-1 rounded-full border flex items-center gap-1.5 transition-colors"
                                        :class="getRoleBadgeClass(role)">
                                    <i :class="getRoleIcon(role)"></i> {{ getRoleName(role) }}
                                </span>
                                <span v-if="!member.roles || member.roles.length === 0" class="text-[10px] text-surface-600 italic font-bold">
                                    Sem acesso definido
                                </span>
                            </div>
                        </div>

                        <div class="mt-auto pt-3 flex justify-between items-center relative z-10 border-t border-surface-3/20">
                            <div class="flex items-center gap-2">
                                    <span v-if="member.pin" class="flex items-center text-green-400 font-black bg-green-500/10 px-2 py-0.5 rounded-full text-[10px]">
                                    <i class="fa-solid fa-key mr-1.5 text-[8px]"></i> PIN
                                </span>
                                <span v-else class="text-surface-500 flex items-center bg-surface-1 px-2 py-0.5 rounded-full text-[10px]">
                                    <i class="fa-solid fa-lock-open mr-1.5 text-[8px]"></i> Sem PIN
                                </span>
                            </div>
                            <span class="text-[9px] text-surface-500 font-black">ID: {{ member.id }}</span>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div v-if="activeTab === 'roles'" class="flex flex-col h-full overflow-hidden relative animate-fade-in">
            
            <div class="p-4 md:p-6 pb-4 shrink-0 z-10">
                <div class="bg-primary-500/10 p-4 rounded-[20px] text-sm text-primary-200 flex gap-3 items-start border border-primary-500/20">
                    <i class="fa-solid fa-circle-info mt-0.5 text-primary-500"></i>
                    <div>
                        <strong>Controle de Acesso Granular:</strong> Marque o que cada cargo pode fazer.
                    </div>
                </div>
            </div>

            <div class="flex-1 overflow-auto scrollbar-thin px-4 md:px-6 pb-6 relative">
                <div v-if="isLoadingMatrix" class="flex justify-center p-10"><i class="fa-solid fa-spinner fa-spin text-2xl text-surface-500"></i></div>
                
                <table v-else class="w-full text-left border-collapse min-w-[600px]">
                    <thead class="sticky top-0 bg-surface-1 z-20 shadow-sm">
                        <tr>
                            <th class="p-4 border-b border-surface-3/20 text-surface-400 font-black uppercase text-xs tracking-wider w-1/3 bg-surface-1">Funcionalidade</th>
                            <th v-for="role in matrixData" :key="role.slug" class="p-4 border-b border-surface-3/20 text-center min-w-[100px] bg-surface-1">
                                <div class="font-black text-surface-0 text-xs md:text-sm">{{ role.name }}</div>
                                <div class="text-[8px] md:text-[9px] text-surface-500 font-bold">{{ role.slug }}</div>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-3/10">
                        <tr v-for="feat in schema" :key="feat.cap" class="hover:bg-surface-3/30 transition-colors">
                            <td class="p-4">
                                <div class="font-bold text-surface-200 text-sm mb-0.5">{{ feat.label }}</div>
                                <div class="text-xs text-surface-500 leading-snug font-medium hidden md:block">{{ feat.desc }}</div>
                            </td>
                            <td v-for="role in matrixData" :key="role.slug" class="p-4 text-center align-middle">
                                <div class="flex justify-center">
                                    <Checkbox v-model="role.caps[feat.cap]" :binary="true" />
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="p-4 border-t border-surface-3/10 bg-surface-1 shrink-0 flex justify-end">
                <Button label="Salvar Permissões" icon="fa-solid fa-save" class="!bg-primary-500 hover:!bg-primary-400 !border-none !font-black !rounded-full !text-surface-950 shadow-lg !w-full md:!w-auto" :loading="isSavingMatrix" @click="saveMatrix" />
            </div>
        </div>

    </div>

    <Dialog 
        v-model:visible="showModal" 
        modal 
        :header="form.id ? 'Editar Colaborador' : 'Adicionar Colaborador'" 
        :style="{ width: '550px', maxWidth: '90vw' }" 
        :pt="{ 
            root: { class: '!bg-surface-1 !border-none !rounded-[28px] !shadow-2xl' }, 
            header: { class: '!bg-transparent !text-surface-0 !border-b !border-surface-3/10 !p-6' }, 
            content: { class: '!bg-transparent !p-6' }
        }"
    >
        <form @submit.prevent="save" class="flex flex-col gap-5" autocomplete="off">
            <div v-if="!form.id" class="bg-primary-500/10 border border-primary-500/20 p-4 rounded-[16px] text-xs text-primary-300 flex gap-3">
                <i class="fa-solid fa-circle-info text-lg mt-0.5"></i>
                <div class="leading-relaxed">
                    <strong>Dica:</strong> Digite o e-mail de um cliente existente para transformá-lo em colaborador.
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-black text-surface-400">E-mail (Login)</label>
                    <div class="relative">
                        <i class="fa-solid fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-surface-500"></i>
                        <InputText v-model="form.email" class="!pl-10 !w-full !rounded-full !h-11" :disabled="!!form.id" placeholder="email@exemplo.com" />
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-sm font-black text-surface-400">Nome</label>
                    <div class="relative">
                        <i class="fa-solid fa-user absolute left-4 top-1/2 -translate-y-1/2 text-surface-500"></i>
                        <InputText v-model="form.name" class="!pl-10 !w-full !rounded-full !h-11" placeholder="Nome Completo" />
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-black text-surface-400">Atribuições (Múltipla Escolha)</label>
                <div class="grid grid-cols-1 gap-2 bg-surface-2 p-2 rounded-[20px] max-h-60 overflow-y-auto scrollbar-thin">
                    <div v-for="roleDef in availableRoles" :key="roleDef.value"
                        class="flex items-center p-3 rounded-2xl transition-all cursor-pointer group hover:bg-surface-3"
                        :class="form.roles.includes(roleDef.value) ? 'bg-primary-500/10 shadow-inner' : 'bg-transparent'"
                        @click="toggleRole(roleDef.value)"
                    >
                        <div class="w-5 h-5 rounded-full border flex items-center justify-center mr-3 transition-colors shrink-0"
                             :class="form.roles.includes(roleDef.value) ? 'bg-primary-500 border-primary-500' : 'border-surface-500 group-hover:border-surface-400'">
                            <i v-if="form.roles.includes(roleDef.value)" class="fa-solid fa-check text-surface-950 text-[10px] font-black"></i>
                        </div>
                        <div class="w-10 h-10 rounded-[12px] bg-surface-1 flex items-center justify-center mr-3 text-surface-400 shrink-0">
                            <i class="fa-solid text-lg" :class="roleDef.icon"></i>
                        </div>
                        <div class="flex-1">
                            <div class="font-bold text-sm text-surface-0">{{ roleDef.label }}</div>
                            <div class="text-[10px] text-surface-500 font-bold">{{ roleDef.desc }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-2" v-if="canHaveKitchen(form.roles)">
                <label class="text-sm font-black text-surface-400">Cozinha / Estação Principal</label>
                <div class="bg-surface-2 p-1 rounded-full border border-surface-3/10">
                    <Select 
                        v-model="form.main_kitchen" 
                        :options="kitchens" 
                        optionLabel="name" 
                        optionValue="id"
                        placeholder="Selecione a estação padrão..."
                        class="w-full !bg-transparent !border-none !text-sm !font-bold !h-10 flex items-center"
                        :pt="{
                            label: { class: '!text-surface-0 !px-4' },
                            dropdown: { class: '!text-surface-400' },
                            overlay: { class: '!bg-surface-2 !border !border-surface-3/20 !rounded-xl !shadow-2xl' },
                            option: { class: '!text-surface-300 hover:!bg-surface-3 hover:!text-surface-0 !font-bold !text-xs !p-3 !rounded-lg !m-1' }
                        }"
                    />
                </div>
                <p class="text-[10px] text-surface-500 font-bold ml-2">
                    * Define a visão padrão do KDS e notificações.
                </p>
            </div>

            <div class="flex flex-col gap-2">
                <label class="text-sm font-black text-surface-400 flex justify-between">
                    <span>PIN de Acesso Rápido (PDV)</span>
                    <span class="text-[10px] text-surface-500 uppercase tracking-wider font-bold">Opcional</span>
                </label>
                <div class="relative">
                    <InputText 
                        v-model="form.pin" 
                        type="password"
                        maxlength="4" 
                        placeholder="••••" 
                        class="!text-center !tracking-[1em] !text-xl !h-12 !rounded-full !w-full" 
                        autocomplete="new-password"
                    />
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-4 pt-6 border-t border-surface-3/10">
                <Button label="Cancelar" type="button" text class="!text-surface-400 hover:!text-surface-0 !font-black !rounded-full" @click="showModal = false" />
                <Button label="Salvar Acessos" type="submit" icon="fa-solid fa-check" class="!bg-primary-500 hover:!bg-primary-400 !border-none !font-black !rounded-full !text-surface-950 shadow-lg" :loading="isSaving" />
            </div>
        </form>
    </Dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive, computed } from 'vue';
import { useTeamStore } from '@/stores/team-store';
import { useFormat } from '@/composables/useFormat'; 
import { useMobile } from '@/composables/useMobile'; 
import api from '@/services/api';
import { notify } from '@/services/notify';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';

const store = useTeamStore();
const { abbreviateName } = useFormat(); 
const { isMobile } = useMobile();
const showModal = ref(false);
const isSaving = ref(false);
const kitchens = ref([]);

const activeTab = ref('members');
const tabs = [
    { id: 'members', label: 'Colaboradores', icon: 'fa-solid fa-users' },
    { id: 'roles', label: 'Permissões', icon: 'fa-solid fa-user-shield' }
];

const tabIndicatorStyle = computed(() => {
    const index = tabs.findIndex(t => t.id === activeTab.value);
    const itemWidth = 56; // w-14
    return {
        transform: `translateX(${index * itemWidth + 8}px)` // 8px de offset para centralizar
    };
});

const availableRoles = [
    { label: 'Super Admin (Proprietário)', value: 'administrator', icon: 'fa-solid fa-crown', desc: 'Acesso total' },
    { label: 'Gestão (Gerente)', value: 'nativa_manager', icon: 'fa-solid fa-user-shield', desc: 'PDV, Financeiro e Configs' },
    { label: 'Atendimento (Garçom)', value: 'nativa_waiter', icon: 'fa-solid fa-mobile-screen-button', desc: 'Mesas e Delivery' },
    { label: 'Entrega (Motoboy)', value: 'nativa_driver', icon: 'fa-solid fa-motorcycle', desc: 'App de entregas' },
    { label: 'Cozinha (KDS)', value: 'nativa_kitchen', icon: 'fa-solid fa-fire-burner', desc: 'Tela de produção' }
];

const form = reactive({ id: null, name: '', email: '', roles: [], pin: '', main_kitchen: null });
const schema = ref([]);
const matrixData = ref([]);
const isLoadingMatrix = ref(false);
const isSavingMatrix = ref(false);

const fetchMatrix = async () => { isLoadingMatrix.value = true; try { const { data } = await api.get('/roles-matrix'); if(data.success) { schema.value = data.schema; matrixData.value = data.matrix; } } catch(e) { console.error(e); } finally { isLoadingMatrix.value = false; } };
const saveMatrix = async () => { isSavingMatrix.value = true; try { const { data } = await api.post('/roles-matrix', { matrix: matrixData.value }); if(data.success) notify('success', 'Sucesso', 'Permissões atualizadas.'); } catch(e) { notify('error', 'Erro', 'Falha ao salvar.'); } finally { isSavingMatrix.value = false; } };

const fetchKitchens = async () => {
    try {
        const { data } = await api.get('/logistica/cozinhas');
        if (data.success) kitchens.value = data.cozinhas;
    } catch(e) { console.error(e); }
};

const getRoleIcon = (role) => availableRoles.find(r => r.value === role)?.icon || 'fa-solid fa-user';
const getRoleName = (role) => (role === 'administrator' ? 'Super Admin' : availableRoles.find(r => r.value === role)?.label.split('(')[0].trim() || role);
const getRoleBadgeClass = (role) => {
    switch(role) {
        case 'administrator': return 'bg-purple-500/10 text-purple-400 border-purple-500/20';
        case 'nativa_manager': return 'bg-blue-500/10 text-blue-400 border-blue-500/20';
        case 'nativa_waiter': return 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20';
        case 'nativa_driver': return 'bg-green-500/10 text-green-400 border-green-500/20';
        case 'nativa_kitchen': return 'bg-orange-500/10 text-orange-400 border-orange-500/20';
        default: return 'bg-surface-3 text-surface-400 border-surface-3/50';
    }
};

const canHaveKitchen = (roles) => {
    return roles.some(r => ['nativa_kitchen', 'nativa_waiter', 'nativa_manager', 'administrator'].includes(r));
};

const toggleRole = (roleValue) => { const idx = form.roles.indexOf(roleValue); idx > -1 ? form.roles.splice(idx, 1) : form.roles.push(roleValue); };
const openModal = (member = null) => {
    if (member) { 
        form.id = member.id; 
        form.name = member.name; 
        form.email = member.email; 
        form.pin = member.pin; 
        form.roles = Array.isArray(member.roles) ? [...member.roles] : (member.role ? [member.role] : []);
        form.main_kitchen = member.main_kitchen || null;
    } 
    else { 
        Object.assign(form, { id: null, name: '', email: '', roles: ['nativa_waiter'], pin: '', main_kitchen: null }); 
    }
    showModal.value = true;
};
const save = async () => { 
    if (!form.email || form.roles.length === 0) { notify('warn', 'Atenção', 'E-mail e Cargo obrigatórios.'); return; } 
    isSaving.value = true; 
    // Passa o campo main_kitchen junto com os dados
    if(await store.saveMember(form)) showModal.value = false; 
    isSaving.value = false; 
};

onMounted(() => { 
    store.fetchTeam(); 
    fetchMatrix(); 
    fetchKitchens();
});
</script>

<style scoped>
.animate-fade-in { animation: fadeIn 0.3s ease-out; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

/* Estilos de transição */
.scale-enter-active, .scale-leave-active { transition: all 0.2s ease; }
.scale-enter-from, .scale-leave-to { transform: scale(0); opacity: 0; }

.scrollbar-thin::-webkit-scrollbar { width: 6px; }
.scrollbar-thin::-webkit-scrollbar-thumb { background-color: theme('colors.surface.600'); border-radius: 99px; }
.scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
</style>