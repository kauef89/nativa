<template>
    <div class="flex flex-col h-full w-full overflow-hidden bg-surface-1 rounded-[24px] transition-all">
        
        <div class="shrink-0 h-20 px-6 flex items-center justify-between z-20 bg-surface-1 border-b border-surface-3/10">
            <div>
                <h1 class="text-xl md:text-2xl font-black text-surface-0 flex items-center gap-2">
                    <i class="fa-solid fa-star text-yellow-500"></i>
                    Fidelidade
                </h1>
                <p class="text-surface-400 text-xs font-bold hidden md:block">Defina as taxas do programa de pontos.</p>
            </div>
            
            <Button 
                label="Salvar Configurações" 
                icon="fa-solid fa-check" 
                class="hidden md:flex !bg-primary-500 hover:!bg-primary-400 !text-surface-950 !font-black !border-none !rounded-full shadow-lg"
                :loading="loading"
                @click="saveConfigs"
            />

            <Button 
                icon="fa-solid fa-check" 
                rounded
                class="md:hidden !w-10 !h-10 !bg-primary-500 !text-surface-950 !border-none !shadow-lg shrink-0"
                :loading="loading"
                @click="saveConfigs" 
            />
        </div>

        <div class="flex-1 overflow-y-auto p-4 md:p-6 scrollbar-thin">
            <div class="max-w-4xl mx-auto space-y-6">
                
                <div class="bg-surface-2 rounded-[24px] p-4 md:p-6 shadow-lg border border-surface-3/10">
                    <div class="flex items-center justify-between p-4 bg-surface-1 rounded-[20px] border border-surface-3/10">
                        <div class="flex items-center gap-3 md:gap-4">
                            <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-primary-500/10 flex items-center justify-center text-primary-500 border border-primary-500/20 shrink-0">
                                <i class="fa-solid fa-power-off text-lg md:text-xl"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-sm md:text-base text-surface-0 font-black leading-tight">Programa Ativo</span>
                                <span class="text-[10px] md:text-xs text-surface-500 uppercase font-bold tracking-wider">Habilita acúmulo e resgate</span>
                            </div>
                        </div>
                        <ToggleSwitch v-model="form.enabled" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                    
                    <div class="bg-surface-2 rounded-[24px] p-5 md:p-6 space-y-6 shadow-lg border border-surface-3/10">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 h-10 rounded-full bg-blue-500/10 flex items-center justify-center text-blue-400 border border-blue-500/20">
                                <i class="fa-solid fa-calculator"></i>
                            </div>
                            <h2 class="text-lg font-black text-surface-0">Taxas de Conversão</h2>
                        </div>

                        <div class="space-y-4">
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest pl-1">A cada R$ 1,00 gasto ganha:</label>
                                <InputNumber 
                                    v-model="form.points_earn_rate" 
                                    mode="decimal" 
                                    :minFractionDigits="1" 
                                    suffix=" pts" 
                                    class="w-full" 
                                    inputClass="!bg-surface-1 !border-none !text-surface-0 !text-center !font-black !rounded-full !h-12"
                                />
                                <p class="text-[9px] text-surface-500 italic font-bold pl-1">Ex: 1.0 = R$ 1,00 vale 1 ponto.</p>
                            </div>

                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest pl-1">Cada 1 ponto vale:</label>
                                <InputNumber 
                                    v-model="form.points_redeem_rate" 
                                    mode="currency" 
                                    currency="BRL" 
                                    locale="pt-BR" 
                                    class="w-full" 
                                    inputClass="!bg-surface-1 !border-none !text-surface-0 !text-center !font-black !rounded-full !h-12"
                                />
                                <p class="text-[9px] text-surface-500 italic font-bold pl-1">Ex: R$ 1,00 = Item de R$ 20 custa 20 pts.</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-surface-2 rounded-[24px] p-5 md:p-6 space-y-6 shadow-lg border border-surface-3/10">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-10 h-10 rounded-full bg-orange-500/10 flex items-center justify-center text-orange-400 border border-orange-500/20">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>
                            <h2 class="text-lg font-black text-surface-0">Validade e Mínimos</h2>
                        </div>

                        <div class="space-y-4">
                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest pl-1">Saldo Mínimo para Resgate</label>
                                <InputNumber 
                                    v-model="form.min_redeem" 
                                    placeholder="Ex: 100" 
                                    class="w-full" 
                                    inputClass="!bg-surface-1 !border-none !text-surface-0 !text-center !font-black !rounded-full !h-12"
                                />
                            </div>

                            <div class="flex flex-col gap-2">
                                <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest pl-1">Expiração dos Pontos (Dias)</label>
                                <InputNumber 
                                    v-model="form.validity_days" 
                                    placeholder="Ex: 365" 
                                    class="w-full" 
                                    inputClass="!bg-surface-1 !border-none !text-surface-0 !text-center !font-black !rounded-full !h-12"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-primary-500/10 border border-primary-500/20 rounded-[20px] p-4 flex gap-4 items-start">
                    <i class="fa-solid fa-circle-info text-primary-500 mt-1"></i>
                    <div class="text-xs md:text-sm text-surface-300 font-medium leading-relaxed">
                        <p class="font-black text-surface-0 mb-1">Como funciona?</p>
                        Os pontos por resgate são calculados automaticamente. Vá até o <strong>Inventário</strong> para escolher itens de troca. O custo em pontos será o preço do produto dividido pelo valor que cada ponto "compra".
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import api from '@/services/api';
import { notify } from '@/services/notify';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import ToggleSwitch from 'primevue/toggleswitch';

const loading = ref(false);
const form = reactive({ enabled: true, points_earn_rate: 1.0, points_redeem_rate: 1.0, min_redeem: 0, validity_days: 365 });

const loadConfigs = async () => { try { const { data } = await api.get('/settings/loyalty'); if (data.success) Object.assign(form, data.settings); } catch (e) {} };
const saveConfigs = async () => { loading.value = true; try { await api.post('/settings/loyalty', form); notify('success', 'Sucesso', 'Configurações atualizadas!'); } catch (e) { notify('error', 'Erro', 'Falha ao salvar.'); } finally { loading.value = false; } };

onMounted(loadConfigs);
</script>