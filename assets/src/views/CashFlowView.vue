<template>
  <div class="flex flex-col h-full w-full overflow-hidden bg-surface-1 rounded-[24px] transition-all">
    
    <div class="shrink-0 h-20 px-6 flex items-center justify-between z-20 border-b border-surface-3/10">
        <div>
            <h1 class="text-2xl font-black text-surface-0 flex items-center gap-3">
                <i class="fa-solid fa-sack-dollar text-primary-500"></i> Fluxo de Caixa
            </h1>
            <div v-if="store.isOpen" class="text-xs text-green-400 font-bold mt-1 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                Aberto desde {{ formatDate(store.register?.opened_at, true) }}
            </div>
            <div v-else class="text-xs text-red-400 font-bold mt-1 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                Caixa Fechado
            </div>
        </div>

        <div class="flex gap-2">
            <template v-if="store.isOpen">
                <Button 
                    label="Suprimento" 
                    icon="fa-solid fa-arrow-up" 
                    class="!bg-green-500/10 hover:!bg-green-500 !text-green-500 hover:!text-surface-950 !border-green-500/20 !font-black !text-xs !h-9 !rounded-full"
                    @click="openMovement('supply')"
                />
                <Button 
                    label="Sangria" 
                    icon="fa-solid fa-arrow-down" 
                    class="!bg-red-500/10 hover:!bg-red-500 !text-red-500 hover:!text-surface-950 !border-red-500/20 !font-black !text-xs !h-9 !rounded-full"
                    @click="openMovement('bleed')"
                />
                <div class="w-px h-6 bg-surface-3 self-center mx-1"></div>
                <Button 
                    label="Fechar Caixa" 
                    icon="fa-solid fa-lock" 
                    class="!bg-surface-2 hover:!bg-surface-3 !text-surface-300 hover:!text-surface-0 !font-black !text-xs !h-9 !rounded-full !border-none"
                    @click="openClose"
                />
            </template>
            <template v-else>
                 <Button 
                    label="Abrir Caixa" 
                    icon="fa-solid fa-key" 
                    class="!bg-primary-500 hover:!bg-primary-400 !text-surface-950 !font-black !text-xs !h-9 !rounded-full !border-none shadow-lg"
                    @click="openOpen"
                />
            </template>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-6 flex flex-col gap-6">
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-surface-2 p-5 rounded-[24px] relative overflow-hidden group border border-surface-3/10">
                <div class="text-surface-400 text-[10px] font-black uppercase tracking-widest mb-1">Na Gaveta (Dinheiro)</div>
                <div class="text-2xl font-black text-green-400">{{ formatCurrency(store.summary.cash_balance) }}</div>
            </div>
            <div class="bg-surface-2 p-5 rounded-[24px] border border-surface-3/10">
                <div class="text-surface-400 text-[10px] font-black uppercase tracking-widest mb-1">Faturamento Real</div>
                <div class="text-2xl font-black text-surface-0">{{ formatCurrency(store.summary.total_in) }}</div>
            </div>
            <div class="bg-surface-2 p-5 rounded-[24px] border border-surface-3/10">
                <div class="text-surface-400 text-[10px] font-black uppercase tracking-widest mb-1">Cortesias / Perdas</div>
                <div class="text-2xl font-black text-purple-400">{{ formatCurrency(store.summary.total_loss || 0) }}</div>
            </div>
            <div class="bg-surface-2 p-5 rounded-[24px] border border-surface-3/10">
                <div class="text-surface-400 text-[10px] font-black uppercase tracking-widest mb-1">Saídas / Sangrias</div>
                <div class="text-2xl font-black text-red-400">- {{ formatCurrency(store.summary.total_out) }}</div>
            </div>
        </div>

        <div class="flex-1 bg-surface-2 rounded-[24px] overflow-hidden flex flex-col min-h-[400px] border border-surface-3/10 shadow-lg">
            
            <div class="p-4 flex flex-wrap gap-2 items-center z-10 bg-surface-2 border-b border-surface-3/10">
                <span class="text-surface-400 text-xs font-bold mr-2 uppercase tracking-widest"><i class="fa-solid fa-filter mr-1"></i> Filtrar:</span>
                <FilterChip label="Todos" :active="filter === 'all'" @click="filter = 'all'" />
                <FilterChip label="Vendas" icon="fa-solid fa-cart-shopping" :active="filter === 'sale'" @click="filter = 'sale'" />
                <FilterChip label="Saídas" icon="fa-solid fa-arrow-down" :active="filter === 'out'" @click="filter = 'out'" />
                <FilterChip label="Estornos" icon="fa-solid fa-rotate-left" color="red" :active="filter === 'reversal'" @click="filter = 'reversal'" />
            </div>

            <div class="flex-1 overflow-y-auto">
                <DataTable :value="filteredTransactions" :rowHover="true" responsiveLayout="scroll"
                    class="p-datatable-sm text-sm"
                    :pt="{
                        headerRow: { class: '!bg-surface-2 !text-surface-400 !text-[10px] !uppercase !font-black !tracking-widest !border-b !border-surface-3/10' },
                        bodyRow: { class: '!bg-transparent hover:!bg-surface-3 !text-surface-200 !transition-colors !border-b !border-surface-3/10 last:!border-0' },
                        bodyCell: { class: '!py-3' }
                    }"
                >
                    <Column field="created_at" header="Horário" class="w-40">
                        <template #body="slotProps">
                            <span class="font-bold text-xs whitespace-nowrap">{{ formatDate(slotProps.data.created_at, true) }}</span>
                        </template>
                    </Column>

                    <Column field="type" header="Operação" class="w-32">
                        <template #body="slotProps">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-xs" :class="getTypeIconClass(slotProps.data.type)">
                                    <i :class="getTypeIcon(slotProps.data.type)"></i>
                                </div>
                                <span class="font-bold text-xs uppercase">{{ translateType(slotProps.data.type) }}</span>
                            </div>
                        </template>
                    </Column>
                    
                    <Column field="method" header="Pagamento" class="w-40">
                        <template #body="slotProps">
                            <div class="flex items-center gap-2">
                                <i class="text-surface-400 text-sm" :class="getMethodIcon(slotProps.data.method)"></i>
                                <span class="font-bold text-sm text-surface-200 truncate" :title="slotProps.data.method">
                                    {{ getMethodLabel(slotProps.data.method) }}
                                </span>
                            </div>
                        </template>
                    </Column>
                    
                    <Column field="description" header="Descrição">
                        <template #body="slotProps">
                            <div class="truncate max-w-[200px] md:max-w-md lg:max-w-xl font-medium text-sm text-surface-100" :title="formatDescription(slotProps.data)">
                                <span v-if="slotProps.data.type === 'sale'" class="text-primary-400 font-bold mr-1">#{{ slotProps.data.session_id }}</span>
                                <span class="text-surface-300">{{ formatDescription(slotProps.data) }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column header="Fiscal" class="w-20 text-center">
                        <template #body="slotProps">
                            <div v-if="slotProps.data.type === 'sale'" class="flex justify-center">
                                <span v-if="slotProps.data.fiscal_status === 'emitido'" class="text-green-500 text-lg" v-tooltip="'NFC-e Autorizada'">
                                    <i class="fa-solid fa-circle-check"></i>
                                </span>
                                <span v-else-if="slotProps.data.fiscal_status === 'erro'" class="text-red-500 text-lg" v-tooltip="'Erro de Emissão'">
                                    <i class="fa-solid fa-circle-exclamation"></i>
                                </span>
                                <span v-else-if="slotProps.data.fiscal_status === 'cancelado'" class="text-orange-500 text-lg" v-tooltip="'Nota Cancelada'">
                                    <i class="fa-solid fa-ban"></i>
                                </span>
                                <span v-else class="text-surface-600 text-lg" v-tooltip="'Não Emitida'">
                                    <i class="fa-regular fa-circle"></i>
                                </span>
                            </div>
                        </template>
                    </Column>
                    
                    <Column field="amount" header="Valor" class="text-right w-28">
                        <template #body="slotProps">
                            <span class="font-black text-sm whitespace-nowrap" :class="getValueClass(slotProps.data.type, slotProps.data.amount)">
                                {{ getSign(slotProps.data.type) }} {{ formatCurrency(slotProps.data.amount) }}
                            </span>
                        </template>
                    </Column>

                    <Column header="Ações" class="w-32 text-right">
                        <template #body="slotProps">
                            <div class="flex justify-end gap-1" v-if="slotProps.data.type === 'sale'">
                                <Button icon="fa-solid fa-eye" text rounded size="small" 
                                    class="!w-8 !h-8 !text-surface-300 hover:!text-primary-400 hover:!bg-surface-3" 
                                    v-tooltip.top="'Ver Detalhes'" @click="handleViewOrder(slotProps.data)" />

                                <Button icon="fa-solid fa-receipt" text rounded size="small" 
                                    class="!w-8 !h-8 !text-surface-400 hover:!text-surface-0 hover:!bg-surface-3" 
                                    v-tooltip.top="'Recibo Simples'" @click="printReceipt(slotProps.data)" />
                                
                                <Button v-if="slotProps.data.fiscal_status === 'emitido'" icon="fa-solid fa-file-pdf" text rounded size="small" 
                                    class="!w-8 !h-8 !text-green-500 hover:!bg-green-500/10" 
                                    v-tooltip.top="'Visualizar DANFE'" @click="printFiscal(slotProps.data)" />
                                <Button v-else icon="fa-solid fa-qrcode" text rounded size="small" 
                                    class="!w-8 !h-8 !text-surface-400 hover:!text-orange-400 hover:!bg-surface-3" 
                                    :loading="emittingId === slotProps.data.id"
                                    v-tooltip.top="'Emitir NFC-e'" @click="emitFiscal(slotProps.data)" />

                                <Button icon="fa-solid fa-ban" text rounded size="small" 
                                    class="!w-8 !h-8 !text-red-400 hover:!text-red-200 hover:!bg-red-500/20" 
                                    v-tooltip.top="'Estornar Venda'" @click="openCancelModal(slotProps.data)" />
                            </div>
                        </template>
                    </Column>

                </DataTable>
            </div>
        </div>
    </div>

    <Dialog v-model:visible="modals.open" modal header="Abertura de Caixa" :style="{ width: '800px' }"
        :pt="{ root: { class: '!bg-surface-1 !border-none !rounded-[24px]' }, header: { class: '!bg-transparent !p-6 !text-surface-0 !border-b !border-surface-3/10' }, content: { class: '!bg-transparent !p-0' } }">
        
        <div class="h-[500px] flex flex-col">
            <div class="flex-1 overflow-y-auto p-6 scrollbar-thin">
                <p class="text-sm text-surface-400 font-bold mb-4">Informe a contagem inicial do fundo de troco:</p>
                <CashControl v-model="openBreakdown" @change="val => openTotal = val.total" />
            </div>
            
            <div class="p-6 border-t border-surface-3/10 bg-surface-1 flex justify-between items-center gap-4 shrink-0">
                <div class="flex flex-col">
                    <span class="text-[10px] uppercase font-black text-surface-500 tracking-widest">Total Abertura</span>
                    <span class="text-2xl font-black text-green-400">{{ formatCurrency(openTotal) }}</span>
                </div>
                <Button 
                    label="CONFIRMAR E ABRIR" 
                    icon="fa-solid fa-check" 
                    class="!bg-green-600 hover:!bg-green-500 !border-none !font-black !h-12 !text-base !rounded-full shadow-lg shadow-green-900/20 px-8"
                    :loading="loadingAction"
                    @click="handleOpenSubmit"
                />
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="modals.movement" modal :header="moveForm.type === 'supply' ? 'Novo Suprimento' : 'Nova Sangria'" :style="{ width: '400px' }"
        :pt="{ root: { class: '!bg-surface-1 !border-none !rounded-[24px]' }, header: { class: '!bg-transparent !p-6' }, content: { class: '!bg-transparent !p-6' } }">
        <div class="flex flex-col gap-4">
            <div class="p-3 rounded-xl border flex gap-3 items-center" :class="moveForm.type === 'supply' ? 'bg-green-500/10 border-green-500/30' : 'bg-red-500/10 border-red-500/30'">
                <div class="w-10 h-10 rounded-full flex items-center justify-center" :class="moveForm.type === 'supply' ? 'bg-green-500 text-surface-950' : 'bg-red-500 text-surface-950'">
                    <i class="fa-solid text-lg" :class="moveForm.type === 'supply' ? 'fa-arrow-up' : 'fa-arrow-down'"></i>
                </div>
                <div>
                    <h3 class="font-black text-surface-0 text-sm">{{ moveForm.type === 'supply' ? 'Entrada de Valor' : 'Retirada de Valor' }}</h3>
                    <p class="text-[10px] text-surface-300 font-bold uppercase">{{ moveForm.type === 'supply' ? 'Adicionar troco' : 'Pagamento / Retirada' }}</p>
                </div>
            </div>

            <div>
                <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest block mb-2">Valor</label>
                <InputNumber v-model="moveForm.amount" mode="currency" currency="BRL" locale="pt-BR" placeholder="R$ 0,00" class="w-full" :inputClass="'!bg-surface-2 !border-none !text-surface-0 !text-2xl !font-black !text-center !h-14 !rounded-xl'" autoFocus />
            </div>

            <div>
                <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest block mb-2">Motivo / Descrição</label>
                <Textarea v-model="moveForm.desc" rows="2" placeholder="Ex: Pagamento Fornecedor..." class="!bg-surface-2 !border-none !text-surface-0 !text-sm !rounded-xl w-full" />
            </div>

            <Button label="Confirmar" icon="fa-solid fa-check" class="!border-none !w-full !font-black !h-12 !rounded-full shadow-lg" 
                :class="moveForm.type === 'supply' ? '!bg-green-600 hover:!bg-green-500' : '!bg-red-600 hover:!bg-red-500'"
                :loading="loadingAction" @click="handleMovementSubmit" 
            />
        </div>
    </Dialog>

    <Dialog v-model:visible="modals.close" modal header="Fechar Caixa" :style="{ width: '900px' }"
        :pt="{ root: { class: '!bg-surface-1 !border-none !rounded-[24px]' }, header: { class: '!bg-transparent !p-6 !text-surface-0 !border-b !border-surface-3/10' }, content: { class: '!bg-transparent !p-0' } }">
        
        <div class="h-[650px] flex flex-col">
            <div class="p-6 bg-surface-2/30 grid grid-cols-2 gap-4 shrink-0 border-b border-surface-3/10">
                <div class="bg-surface-2 p-4 rounded-[20px]">
                    <span class="text-surface-400 text-[10px] font-bold uppercase block mb-1 tracking-widest">Esperado (Sistema)</span>
                    <span class="text-2xl font-black text-surface-200">{{ formatCurrency(store.summary.cash_balance) }}</span>
                </div>
                <div class="bg-surface-2 p-4 rounded-[20px] border border-primary-500/20">
                    <span class="text-surface-400 text-[10px] font-bold uppercase block mb-1 tracking-widest">Conferido (Você)</span>
                    <span class="text-2xl font-black text-primary-400">{{ formatCurrency(closeTotal) }}</span>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-6 scrollbar-thin">
                <p class="text-sm text-surface-400 font-bold mb-4">Realize a contagem física do dinheiro:</p>
                <CashControl v-model="closeBreakdown" @change="val => closeTotal = val.total" />
                
                <div class="mt-6 pt-6 border-t border-surface-3/10">
                    <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest block mb-2">Observações do Fechamento</label>
                    <Textarea v-model="closeForm.notes" rows="2" placeholder="Justifique eventuais diferenças..." class="!bg-surface-2 !border-none !text-surface-0 !text-sm !rounded-xl w-full" />
                </div>
            </div>

            <div class="p-6 border-t border-surface-3/10 bg-surface-1 flex justify-between items-center gap-4 shrink-0">
                 <div v-if="closingDiff !== 0" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-black" 
                    :class="closingDiff > 0 ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400'">
                    <i class="fa-solid" :class="closingDiff > 0 ? 'fa-arrow-up' : 'fa-arrow-down'"></i>
                    Diferença: {{ closingDiff > 0 ? 'Sobrando' : 'Faltando' }} {{ formatCurrency(Math.abs(closingDiff)) }}
                </div>
                <div v-else class="text-green-400 font-bold text-xs flex items-center gap-2">
                    <i class="fa-solid fa-check-circle"></i> Caixa Batendo
                </div>

                <Button 
                    label="ENCERRAR CAIXA E SAIR" 
                    icon="fa-solid fa-lock" 
                    class="!bg-red-600 hover:!bg-red-500 !border-none !font-black !h-12 !text-base !rounded-full shadow-lg shadow-red-900/20 px-6"
                    :loading="loadingAction"
                    @click="handleCloseSubmit"
                />
            </div>
        </div>
    </Dialog>

    <Dialog v-model:visible="cancelModal.visible" modal header="Estornar Venda" :style="{ width: '400px' }"
        :pt="{ root: { class: '!bg-surface-1 !border-none !rounded-[24px]' }, header: { class: '!bg-transparent !p-6' }, content: { class: '!bg-transparent !p-6' } }">
        <div class="flex flex-col gap-4">
            <div class="bg-red-500/10 p-4 rounded-xl border border-red-500/20 flex items-start gap-3">
                <i class="fa-solid fa-triangle-exclamation text-red-500 mt-1"></i>
                <div class="text-xs text-red-200 leading-relaxed">
                    <strong>Atenção:</strong> Isso reverterá o valor do caixa e reabrirá a sessão para edição.
                </div>
            </div>
            <div class="flex flex-col gap-2">
                <label class="text-[10px] font-black text-surface-400 uppercase tracking-widest">Motivo</label>
                <div class="flex gap-2 flex-wrap">
                    <span v-for="r in reasons" :key="r" 
                          class="px-3 py-1.5 rounded-lg text-[10px] font-bold cursor-pointer border transition-all"
                          :class="cancelModal.reason === r ? 'bg-primary-500 text-surface-950 border-primary-500' : 'bg-surface-2 text-surface-400 border-surface-3 hover:bg-surface-3'"
                          @click="cancelModal.reason = r">
                        {{ r }}
                    </span>
                </div>
                <Textarea v-model="cancelModal.customReason" rows="2" placeholder="Outro motivo..." class="!bg-surface-2 !border-none !text-surface-0 !text-sm !rounded-xl w-full mt-2" />
            </div>
            <Button label="Confirmar Estorno" icon="fa-solid fa-ban" 
                    class="!bg-red-600 hover:!bg-red-500 !border-none !w-full !font-black !rounded-full !h-12 shadow-lg" 
                    :loading="cancelModal.loading"
                    :disabled="!cancelModal.reason && !cancelModal.customReason"
                    @click="confirmCancellation" />
        </div>
    </Dialog>

    <Dialog v-model:visible="showViewModal" modal header="Detalhes da Venda" :style="{ width: '450px' }"
        :pt="{ root: { class: '!bg-surface-1 !border-none !rounded-[24px]' }, header: { class: '!bg-transparent !p-6 !pb-2 !text-surface-0 !border-b !border-surface-3/10' }, content: { class: '!bg-transparent !p-0' } }">
        
        <div v-if="viewOrderData" class="flex flex-col h-full bg-surface-1">
            <div class="p-6 pb-2 border-b border-surface-3/10 border-dashed">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <div class="text-[10px] font-black uppercase text-surface-500 tracking-widest">Pedido #{{ viewOrderData.id }}</div>
                        <div class="text-lg font-black text-surface-0">{{ viewOrderData.customer.name || 'Cliente Balcão' }}</div>
                        <div class="text-xs text-surface-400 font-bold mt-0.5">{{ viewOrderData.date }}</div>
                    </div>
                    <div class="bg-surface-2 px-3 py-1 rounded-full border border-surface-3/20">
                        <span class="text-[10px] font-black uppercase text-surface-300 tracking-wider">
                            {{ viewOrderData.modality === 'delivery' ? 'Entrega' : (viewOrderData.modality === 'pickup' ? 'Retirada' : 'Mesa/Balcão') }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto p-6 scrollbar-thin space-y-2 max-h-[50vh]">
                <div v-for="(item, idx) in viewOrderData.items" :key="idx" class="flex justify-between items-start text-sm">
                    <div class="flex gap-2">
                        <span class="font-bold text-surface-400">{{ item.qty }}x</span>
                        <div class="flex flex-col">
                            <span class="font-bold text-surface-200">{{ item.name }}</span>
                            <div v-if="item.modifiers && item.modifiers.length" class="text-[10px] text-surface-500 italic">
                                <span v-for="mod in item.modifiers" :key="mod.name">+ {{ mod.name }} </span>
                            </div>
                        </div>
                    </div>
                    <span class="font-bold text-surface-200">{{ formatCurrency(item.line_total) }}</span>
                </div>
            </div>

            <div class="p-6 bg-surface-2/30 border-t border-surface-3/10 border-dashed space-y-2">
                <div class="flex justify-between text-xs text-surface-400 font-bold">
                    <span>Subtotal</span>
                    <span>{{ formatCurrency(viewOrderData.totals.subtotal) }}</span>
                </div>
                <div v-if="viewOrderData.totals.fee > 0" class="flex justify-between text-xs text-surface-400 font-bold">
                    <span>Taxa Entrega</span>
                    <span>{{ formatCurrency(viewOrderData.totals.fee) }}</span>
                </div>
                <div class="border-t border-surface-3/20 my-2"></div>
                <div class="flex justify-between items-center">
                    <span class="text-sm font-black text-surface-0 uppercase tracking-wide">Total Pago</span>
                    <span class="text-xl font-black text-primary-400">{{ formatCurrency(viewOrderData.totals.total) }}</span>
                </div>

                <div v-if="viewOrderData.payment" class="mt-4 pt-4 border-t border-surface-3/20 flex flex-wrap gap-2">
                    <div v-for="(pay, pIdx) in (Array.isArray(viewOrderData.payment) ? viewOrderData.payment : [viewOrderData.payment])" :key="pIdx"
                         class="bg-surface-1 px-3 py-1.5 rounded-lg border border-surface-3/10 flex items-center gap-2"
                    >
                        <i class="text-[10px] text-surface-400" :class="getMethodIcon(pay.method)"></i>
                        <span class="text-[10px] font-bold text-surface-300 uppercase">{{ getMethodLabel(pay.method) }}</span>
                        <span class="text-[10px] font-black text-surface-0">{{ formatCurrency(pay.amount) }}</span>
                    </div>
                </div>
            </div>
            
            <div class="p-6 pt-0">
                <Button label="Fechar" class="!w-full !bg-surface-2 hover:!bg-surface-3 !text-surface-300 hover:!text-surface-0 !font-bold !border-none !rounded-full !h-10" @click="showViewModal = false" />
            </div>
        </div>

        <div v-else class="flex justify-center p-10">
            <i class="fa-solid fa-circle-notch fa-spin text-3xl text-primary-500"></i>
        </div>
    </Dialog>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue';
import { useRouter } from 'vue-router'; 
import { useCashStore } from '@/stores/cash-store';
import { useSessionStore } from '@/stores/session-store'; 
import { useFormat } from '@/composables/useFormat';
import Dialog from 'primevue/dialog';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import CashControl from '@/components/manager/CashControl.vue'; 
import { PrinterService } from '@/services/printer-service';
import api from '@/services/api';
import { notify } from '@/services/notify';

// Chip Component Inline
const FilterChip = {
    props: ['label', 'icon', 'active', 'color'],
    template: `
        <button class="px-3 py-1.5 rounded-full text-[10px] font-black uppercase tracking-wider transition-all flex items-center gap-2"
            :class="active 
                ? 'bg-primary-500 text-surface-950 shadow-md' 
                : 'bg-surface-3 text-surface-400 hover:text-surface-200'">
            <i v-if="icon" :class="icon"></i> {{ label }}
        </button>
    `
};

const store = useCashStore();
const sessionStore = useSessionStore();
const router = useRouter();
const { formatCurrency, formatDate } = useFormat(); 
const filter = ref('all');
const emittingId = ref(null);
const loadingAction = ref(false);

const modals = reactive({ open: false, movement: false, close: false });
const openBreakdown = ref({ bills: [], coins: [] });
const closeBreakdown = ref({ bills: [], coins: [] });
const openTotal = ref(0);
const closeTotal = ref(0);
const moveForm = reactive({ type: 'supply', amount: 0, desc: '' });
const closeForm = reactive({ notes: '' });
const cancelModal = reactive({ visible: false, transaction: null, loading: false, reason: '', customReason: '' });
const reasons = ['Erro de Lançamento', 'Desistência do Cliente', 'Troca de Forma Pagto', 'Erro no Sistema'];

// New Refs for View Modal
const showViewModal = ref(false);
const viewOrderData = ref(null);

const filteredTransactions = computed(() => {
    if (!store.transactions) return [];
    return store.transactions.filter(t => {
        if (filter.value === 'sale') return t.type === 'sale';
        if (filter.value === 'reversal') return t.type === 'reversal';
        if (filter.value === 'out') return t.type === 'bleed' || t.amount < 0;
        return true; 
    });
});

const closingDiff = computed(() => closeTotal.value - (store.summary.cash_balance || 0));

const openOpen = () => { openBreakdown.value = { bills: [], coins: [] }; openTotal.value = 0; modals.open = true; };
const handleOpenSubmit = async () => { loadingAction.value = true; if (await store.openRegister(openTotal.value)) modals.open = false; loadingAction.value = false; };
const openMovement = (type) => { moveForm.type = type; moveForm.amount = 0; moveForm.desc = ''; modals.movement = true; };
const handleMovementSubmit = async () => { if (moveForm.amount <= 0) return; loadingAction.value = true; if (await store.addMovement(moveForm.type, moveForm.amount, moveForm.desc)) modals.movement = false; loadingAction.value = false; };
const openClose = () => { closeBreakdown.value = { bills: [], coins: [] }; closeTotal.value = 0; closeForm.notes = ''; modals.close = true; };
const handleCloseSubmit = async () => { if (!confirm('Confirma o fechamento do caixa?')) return; loadingAction.value = true; if (await store.closeRegister(closeTotal.value, closeForm.notes, closeBreakdown.value)) { modals.close = false; sessionStore.leaveSession(); router.push('/staff/pdv'); } loadingAction.value = false; };

// --- Formatação e Ícones ---

const getMethodLabel = (raw) => {
    if (!raw) return '';
    const map = {
        'money': 'Dinheiro', 'dinheiro': 'Dinheiro',
        'credit_card': 'Crédito', 'credit': 'Crédito',
        'debit_card': 'Débito', 'debit': 'Débito',
        'pix': 'Pix', 'pix_sicredi': 'Pix Auto',
        'ticket': 'Vale Refeição', 'vr': 'Vale Refeição', 'va': 'Vale Alimentação',
        'loyalty': 'Fidelidade'
    };
    return map[raw.toLowerCase()] || raw; 
};

const getMethodIcon = (rawMethod) => {
    const m = (rawMethod || '').toLowerCase();
    if (m.includes('money') || m.includes('dinheiro')) return 'fa-solid fa-money-bill';
    if (m.includes('credit') || m.includes('débito') || m.includes('crédito') || m.includes('card')) return 'fa-solid fa-credit-card';
    if (m.includes('pix')) return 'fa-brands fa-pix';
    if (m.includes('ticket') || m.includes('vale')) return 'fa-solid fa-ticket';
    return 'fa-solid fa-circle';
};

const formatDescription = (data) => {
    if (data.description && data.description.includes('(')) return data.description;

    if (data.type !== 'sale') return data.description;
    
    let typeLabel = '';
    if (data.session_type === 'table') {
        typeLabel = (!data.table_number || data.table_number == 0) ? 'Balcão' : `Mesa ${data.table_number}`;
    } else if (data.session_type === 'delivery') {
        typeLabel = 'Delivery';
    } else if (data.session_type === 'pickup') {
        typeLabel = 'Retirada';
    } else {
        typeLabel = 'Balcão';
    }
    
    const client = data.client_name ? ` • ${data.client_name}` : '';
    return `[${typeLabel}]${client}`;
};

const translateType = (t) => ({ sale: 'Venda', bleed: 'Sangria', supply: 'Suprimento', opening: 'Abertura', reversal: 'Estorno' }[t] || t);
const getTypeIcon = (t) => { if(t === 'sale') return 'fa-cart-shopping'; if(t==='reversal') return 'fa-rotate-left'; return 'fa-circle'; };
const getTypeIconClass = (t) => { if(t==='reversal') return 'bg-red-500/20 text-red-500'; if(t==='sale') return 'bg-blue-500/20 text-blue-400'; return 'bg-surface-3 text-surface-400'; };
const getValueClass = (t) => { if (t === 'bleed' || t === 'reversal') return 'text-red-400'; if (t === 'loss') return 'text-purple-400 line-through opacity-70'; return 'text-green-400'; };
const getSign = (t) => { if (t === 'bleed') return '-'; if (t === 'loss' || t === 'reversal') return ''; return ''; };

// --- Cancelamento & Impressão ---
const openCancelModal = (transaction) => { cancelModal.transaction = transaction; cancelModal.reason = ''; cancelModal.customReason = ''; cancelModal.visible = true; };
const confirmCancellation = async () => { if (!cancelModal.transaction) return; const finalReason = cancelModal.customReason || cancelModal.reason; cancelModal.loading = true; try { const { data } = await api.post('/cash/cancel-transaction', { transaction_id: cancelModal.transaction.id, reason: finalReason }); if (data.success) { notify('success', 'Estornado', 'Venda revertida com sucesso.'); store.fetchLedger(); store.checkStatus(); cancelModal.visible = false; } } catch (e) { notify('error', 'Erro', e.response?.data?.message || 'Falha ao estornar.'); } finally { cancelModal.loading = false; } };
const getOrderData = async (sessionId) => { try { const { data } = await api.get(`/order-details/${sessionId}`); if(data.success) return data; return null; } catch(e) { notify('error', 'Erro', 'Falha ao buscar dados do pedido.'); return null; } };
const printReceipt = async (transaction) => { const orderData = await getOrderData(transaction.session_id); if (!orderData) return; try { const payload = { id: orderData.id, client_name: orderData.customer.name, client_cpf: orderData.client_cpf, items: orderData.items, totals: orderData.totals, payments: orderData.payment ? [orderData.payment] : [] }; await PrinterService.printPaymentReceipt(payload); notify('success', 'Imprimindo', 'Comprovante enviado.'); } catch(e) { notify('error', 'Erro', 'Falha ao imprimir.'); } };
const emitFiscal = async (transaction) => { if (!transaction.session_id) return; if (!confirm('Emitir NFC-e para esta venda?')) return; emittingId.value = transaction.id; try { const { data } = await api.post('/fiscal/emit-local', { session_id: transaction.session_id }); if (data.success) { notify('success', 'Emitido', 'NFC-e autorizada.'); store.fetchLedger(); if(data.danfe_url) window.open(data.danfe_url, '_blank'); } } catch (e) { notify('error', 'Erro', e.response?.data?.message || 'Falha na emissão.'); } finally { emittingId.value = null; } };
const printFiscal = (t) => { if(t.fiscal_url) window.open(t.fiscal_url, '_blank'); };

// --- NOVA FUNÇÃO DE VISUALIZAÇÃO ---
const handleViewOrder = async (transaction) => {
    showViewModal.value = true;
    viewOrderData.value = null; // Limpa para mostrar loading
    
    const data = await getOrderData(transaction.session_id);
    if (data) {
        viewOrderData.value = data;
    } else {
        showViewModal.value = false; // Fecha se falhar
    }
};

onMounted(() => { store.checkStatus(); store.fetchLedger(); });
</script>