<template>
  <div class="p-6 w-full h-full bg-surface-950 overflow-hidden flex flex-col">
    
    <div class="flex justify-between items-center mb-6 shrink-0">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                <i class="fa-solid fa-gear text-primary-500"></i> Configurações da Loja
            </h1>
            <p class="text-surface-400 text-sm mt-1">Gerencie horários, contatos e status do sistema.</p>
        </div>
        <div class="flex gap-3">
            <Button label="Salvar Tudo" icon="fa-solid fa-save" class="!bg-primary-600 hover:!bg-primary-500 !border-none !font-bold" @click="save" :loading="saving" />
        </div>
    </div>

    <div v-if="store.isLoading" class="flex justify-center p-20">
        <i class="fa-solid fa-spinner fa-spin text-4xl text-primary-500"></i>
    </div>

    <div v-else-if="store.hours" class="flex-1 bg-surface-900 border border-surface-800 rounded-2xl flex flex-col overflow-hidden">
        
        <div class="p-4 bg-surface-950 border-b border-surface-800 flex items-center justify-between">
            <div class="flex flex-col">
                <span class="text-white font-bold text-sm">Status Manual do Sistema</span>
                <span class="text-surface-500 text-xs">Use em emergências ou feriados</span>
            </div>
            <SelectButton v-model="store.hours.manual_override" :options="overrideOptions" optionLabel="label" optionValue="value" :pt="{ button: ({context}) => ({ class: context.active ? '!bg-primary-500 !border-primary-500' : '!bg-surface-800 !border-surface-700' }) }" />
        </div>
        <div class="p-4 bg-surface-950 border-b border-surface-800 flex items-center justify-between bg-purple-500/5">
            <div class="flex flex-col">
                <span class="text-white font-bold text-sm flex items-center gap-2">
                    <i class="fa-solid fa-code text-primary-400"></i> Sempre Aberto (Admin)
                </span>
                <span class="text-surface-500 text-xs">Força "Aberto" apenas para você (dev/teste)</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-[10px] font-bold uppercase tracking-wider transition-colors" 
                      :class="store.hours.admin_always_open ? 'text-primary-400' : 'text-surface-600'">
                    {{ store.hours.admin_always_open ? 'ATIVO' : 'DESLIGADO' }}
                </span>
                <ToggleSwitch v-model="store.hours.admin_always_open" :pt="{ slider: ({props}) => ({ class: props.modelValue ? '!bg-purple-500' : '' }) }" />
            </div>
        </div>
        
        <TabView class="flex-1 flex flex-col overflow-hidden" :pt="{ panelContainer: { class: '!bg-surface-900 !p-0 !h-full !overflow-y-auto' }, nav: { class: '!bg-surface-950 !border-b !border-surface-800' } }">
            
            <TabPanel header="Geral / Contato">
                <div class="p-6 max-w-3xl">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <div class="bg-surface-950 p-4 rounded-xl border border-surface-800">
                            <label class="text-sm font-bold text-surface-400 block mb-2">WhatsApp de Atendimento</label>
                            <div class="flex items-center gap-2">
                                <i class="fa-brands fa-whatsapp text-green-500 text-xl"></i>
                                <InputText v-model="store.contact.whatsapp" placeholder="Ex: 5547999999999" class="w-full !bg-surface-800 !border-surface-700 !text-white" />
                            </div>
                            <small class="text-surface-500 mt-1 block">Apenas números (com DDD e 55).</small>
                        </div>

                        <div class="bg-surface-950 p-4 rounded-xl border border-surface-800">
                            <label class="text-sm font-bold text-surface-400 block mb-2">Endereço da Loja</label>
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-map-pin text-red-500 text-xl"></i>
                                <InputText v-model="store.contact.address" placeholder="Ex: Rua Central, 100" class="w-full !bg-surface-800 !border-surface-700 !text-white" />
                            </div>
                            <small class="text-surface-500 mt-1 block">Exibido no rodapé e mapas.</small>
                        </div>

                        <div class="bg-surface-950 p-4 rounded-xl border border-surface-800">
                            <label class="text-sm font-bold text-surface-400 block mb-2">Instagram (Opcional)</label>
                            <div class="flex items-center gap-2">
                                <i class="fa-brands fa-instagram text-pink-500 text-xl"></i>
                                <InputText v-model="store.contact.instagram" placeholder="@sua.loja" class="w-full !bg-surface-800 !border-surface-700 !text-white" />
                            </div>
                        </div>
                        <div class="bg-surface-950 p-4 rounded-xl border border-surface-800">
                            <label class="text-sm font-bold text-surface-400 block mb-2">Link de Avaliação (Google Maps)</label>
                            <div class="flex items-center gap-2">
                                <i class="fa-brands fa-google text-white text-xl"></i>
                                <InputText v-model="store.contact.google_reviews" placeholder="https://g.page/r/..." class="w-full !bg-surface-800 !border-surface-700 !text-white" />
                            </div>
                            <small class="text-surface-500 mt-1 block">Link direto para as 5 estrelas.</small>
                        </div>
                    </div>
                </div>
            </TabPanel>

            <TabPanel v-for="type in types" :key="type.id" :header="type.label">
                <div class="p-6">
                    <div class="bg-primary-500/10 border border-primary-500/20 p-4 rounded-xl mb-6 flex items-start gap-3">
                        <i class="fa-solid fa-circle-info text-primary-500 mt-1"></i>
                        <div class="text-sm text-surface-200">
                            <strong>{{ type.desc }}</strong><br>
                            Configure os dias e intervalos. Desmarque o dia para definir como "Fechado/Folga".
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div v-for="(dayLabel, dayKey) in days" :key="dayKey" 
                             class="bg-surface-950 border border-surface-800 rounded-xl p-4 flex flex-col gap-3 transition-colors"
                             :class="!store.hours[type.id][dayKey].active ? 'opacity-50 grayscale' : 'border-primary-500/30'"
                        >
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-white uppercase">{{ dayLabel }}</span>
                                <ToggleSwitch v-model="store.hours[type.id][dayKey].active" />
                            </div>
                            
                            <div class="flex items-center gap-2" v-if="store.hours[type.id][dayKey].active">
                                <div class="flex-1">
                                    <label class="text-[10px] uppercase text-surface-500 font-bold block mb-1">Abertura</label>
                                    <input type="time" v-model="store.hours[type.id][dayKey].start" class="w-full bg-surface-800 border border-surface-700 rounded-lg text-white px-2 py-1.5 focus:border-primary-500 outline-none font-mono" />
                                </div>
                                <span class="text-surface-500 mt-4">-</span>
                                <div class="flex-1">
                                    <label class="text-[10px] uppercase text-surface-500 font-bold block mb-1">Fechamento</label>
                                    <input type="time" v-model="store.hours[type.id][dayKey].end" class="w-full bg-surface-800 border border-surface-700 rounded-lg text-white px-2 py-1.5 focus:border-primary-500 outline-none font-mono" />
                                </div>
                            </div>
                            <div v-else class="h-[58px] flex items-center justify-center text-xs font-bold text-surface-500 bg-surface-900 rounded-lg border border-surface-800 border-dashed">
                                FECHADO
                            </div>
                        </div>
                    </div>
                </div>
            </TabPanel>

        </TabView>
    </div>

    <div v-else class="flex flex-col items-center justify-center flex-1 text-surface-500">
        <i class="fa-solid fa-triangle-exclamation text-4xl mb-4 text-orange-500"></i>
        <p>Não foi possível carregar as configurações.</p>
        <Button label="Tentar Novamente" text class="mt-2" @click="store.fetchAllSettings" />
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useSettingsStore } from '@/stores/settings-store';
import Button from 'primevue/button';
import TabView from 'primevue/tabview';
import TabPanel from 'primevue/tabpanel';
import ToggleSwitch from 'primevue/toggleswitch'; // Atualizado para v4
import SelectButton from 'primevue/selectbutton';
import InputText from 'primevue/inputtext';

const store = useSettingsStore();
const saving = ref(false);

const types = [
    { id: 'general', label: 'Horários Gerais', desc: 'Define o funcionamento global.' },
    { id: 'delivery', label: 'Delivery', desc: 'Horário específico para entregas.' },
    { id: 'pickup', label: 'Retirada', desc: 'Horário para retirada no balcão.' }
];

const days = { mon: 'Segunda', tue: 'Terça', wed: 'Quarta', thu: 'Quinta', fri: 'Sexta', sat: 'Sábado', sun: 'Domingo' };

const overrideOptions = [
    { label: 'Automático', value: 'auto' },
    { label: 'Forçar Aberto', value: 'force_open' },
    { label: 'Forçar Fechado', value: 'force_closed' }
];

const save = async () => {
    saving.value = true;
    await store.saveAll();
    saving.value = false;
};

// Carrega tudo ao montar
onMounted(() => store.fetchAllSettings());
</script>