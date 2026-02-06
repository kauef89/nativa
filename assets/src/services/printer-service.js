import { notify } from "@/services/notify";
import { useMenuStore } from "@/stores/menu-store";
import api from "@/services/api";

// Cache local
let printerMappings = [];
let lastFetchTime = 0;
const CACHE_DURATION = 60000 * 5; // 5 minutos

export const PrinterService = {
  /**
   * Busca mapeamento. Se force=true, ignora cache.
   */
  async _refreshMappings(force = false) {
    const now = Date.now();
    if (
      !force &&
      printerMappings.length > 0 &&
      now - lastFetchTime < CACHE_DURATION
    ) {
      return;
    }

    try {
      const { data } = await api.get("/logistica/impressoras");
      if (data.success) {
        printerMappings = data.impressoras;
        lastFetchTime = now;
        console.log(
          "🖨️ [PrinterService] Mapeamento atualizado:",
          printerMappings,
        );
      }
    } catch (e) {
      console.error("Erro ao buscar impressoras:", e);
    }
  },

  _getPrinterConfigForStation(stationId) {
    if (!stationId) return null;

    // Procura a impressora vinculada a esta cozinha (ex: 1474)
    // Usa == para comparar string '1474' com number 1474
    const config = printerMappings.find((p) => p.cozinha_id == stationId);

    if (config) {
      return {
        type: config.interface === "usb" ? "usb" : "network",
        path: config.path,
        name: config.name, // <--- IMPORTANTE: Envia o nome para a Bridge
      };
    }
    return null;
  },

  _expandComboItems(rawItems) {
    const expanded = [];
    const menuStore = useMenuStore();

    rawItems.forEach((item) => {
      const isCombo =
        item.type === "combo" ||
        (item.modifiers && item.modifiers.some((m) => m.type === "combo_item"));

      if (!isCombo) {
        expanded.push(item);
        return;
      }

      const comboQty = parseInt(item.qty || 1);

      if (item.modifiers) {
        let lastParent = null;

        item.modifiers.forEach((mod) => {
          if (mod.type === "combo_item") {
            let targetStation = "default";
            const prodId = mod.product_id || mod.id;

            if (prodId) {
              for (const cat of menuStore.categories) {
                if (cat.products) {
                  const p = cat.products.find((x) => x.id == prodId);
                  if (p && p.station) {
                    targetStation = p.station;
                    break;
                  }
                }
                if (cat.children) {
                  for (const child of cat.children) {
                    const p = child.products.find((x) => x.id == prodId);
                    if (p && p.station) {
                      targetStation = p.station;
                      break;
                    }
                  }
                }
              }
            }

            lastParent = {
              ...mod,
              name: mod.name.replace(/^•\s*/, ""),
              qty: comboQty,
              station_id: targetStation,
              station: targetStation,
              modifiers: [],
              notes: `(Combo ${item.name})`,
            };
            expanded.push(lastParent);
          } else if (mod.type === "combo_modifier" && lastParent) {
            lastParent.modifiers.push({
              ...mod,
              name: mod.name.replace(/^\s*\+\s*/, ""),
            });
          }
        });
      }
    });

    return expanded;
  },

  async printKitchen(order) {
    try {
      await this._refreshMappings(true);

      const menuStore = useMenuStore();
      if (menuStore.categories.length === 0) await menuStore.fetchMenu();

      const stationGroups = {};

      console.log("🕵️ [SERVICE] Imprimindo Cozinha. Tipo:", order.type);

      const rawItems = Array.isArray(order.items) ? order.items : [];
      const items = this._expandComboItems(rawItems);

      console.log("🕵️ [PrinterService] Itens processados:", items);

      items.forEach((item) => {
        // Se vier do backend corrigido, item.station será 1474 (number).
        // Se vier do cache velho, será "frente".
        // Se for "frente" (legado), forçamos null para não quebrar a busca
        let targetStationId = item.station_id || item.station;

        if (targetStationId === "frente" || targetStationId === "default") {
          // Fallback: Tenta achar uma impressora padrão ou usa a primeira da lista
          // Mas idealmente, limpando o cache, isso virá como 1474.
          targetStationId = null;
        }

        // Se não tiver ID definido, tenta pegar do produto no MenuStore (Refresh forçado)
        if (!targetStationId && item.product_id) {
          const fromStore = this._findProductInStore(
            item.product_id,
            menuStore,
          );
          if (
            fromStore &&
            fromStore.station &&
            fromStore.station !== "frente"
          ) {
            targetStationId = fromStore.station;
          }
        }

        // Agrupamento
        const finalStationKey = targetStationId || "default";

        if (!stationGroups[finalStationKey]) {
          stationGroups[finalStationKey] = {
            id: targetStationId, // Pode ser null se for default
            items: [],
          };
        }

        const qty = parseInt(item.qty || 1);
        let catName = "Geral";

        for (let i = 1; i <= qty; i++) {
          stationGroups[finalStationKey].items.push({
            name: item.name || item.product_name,
            modifiers: item.modifiers || [],
            notes: item.notes || "",
            partIndex: i,
            totalParts: qty,
            categoryName: catName,
            categoryOrder: 999,
            table:
              order.type === "table"
                ? order.table_number || order.identifier
                : null,
            client: order.client_name,
            orderId: order.id,
            server_name: order.server_name,
            sessionType: order.type,
            is_takeout: item.is_takeout || false,
          });
        }
      });

      const groupKeys = Object.keys(stationGroups);
      if (groupKeys.length === 0) return;

      let dispatchedCount = 0;

      for (const key of groupKeys) {
        const group = stationGroups[key];
        if (group.items.length === 0) continue;

        // Pega a config usando o ID da cozinha (ex: 1474)
        const printerConfig = this._getPrinterConfigForStation(group.id);

        console.log(
          `🖨️ Estação [${group.id}] -> Printer Config:`,
          printerConfig,
        );

        await this._send({
          type: "kitchen",
          station: `station_${group.id || "default"}`,
          data: {
            tickets: group.items,
            targetPrinter: printerConfig, // Envia { name: "Fundos", ... }
          },
        });
        dispatchedCount++;
      }

      if (dispatchedCount > 0) {
        notify("success", "Enviado", "Comandas enviadas para produção.");
      } else {
        notify("warn", "Atenção", "Nenhum item elegível para impressão.");
      }
    } catch (e) {
      console.error(e);
      notify("error", "Erro de Impressão", "Falha ao enviar para o servidor.");
    }
  },

  // Helper auxiliar para buscar dados frescos caso o item do carrinho esteja velho
  _findProductInStore(id, menuStore) {
    for (const cat of menuStore.categories) {
      if (cat.products) {
        const p = cat.products.find((x) => x.id == id);
        if (p) return p;
      }
      if (cat.children) {
        for (const child of cat.children) {
          const p = child.products.find((x) => x.id == id);
          if (p) return p;
        }
      }
    }
    return null;
  },

  async printAccount(sessionData) {
    // ... (código existente mantido) ...
    try {
      const payload = {
        type: "account",
        station: "frente",
        data: {
          table: sessionData.identifier,
          client: sessionData.client_name,
          orderId: sessionData.sessionId,
          items: sessionData.items,
          totals: sessionData.totals,
        },
      };
      await this._send(payload);
      notify("success", "Imprimindo", "Conferência enviada.");
    } catch (e) {
      console.error(e);
      notify("error", "Erro", "Falha na impressão da conta.");
    }
  },

  async printPaymentReceipt(orderData) {
    // ... (código existente mantido) ...
    try {
      const payload = {
        type: "payment",
        station: "frente",
        data: {
          orderId: orderData.id,
          client: orderData.client_name,
          client_cpf: orderData.client_cpf,
          items: orderData.items,
          totals: orderData.totals,
          payments: orderData.payments,
        },
      };
      await this._send(payload);
      notify("success", "Imprimindo", "Recibo enviado.");
    } catch (e) {
      console.error(e);
      notify("error", "Erro", "Falha ao imprimir recibo.");
    }
  },

  async printDeliveryReport(order) {
    // ... (código existente mantido) ...
    try {
      const payload = {
        type: "delivery",
        station: "frente",
        data: {
          type: order.type,
          orderId: order.id,
          client: order.client_name || order.customer?.name,
          phone: order.client_phone || order.customer?.phone,
          address: order.delivery || order.address,
          items: order.items,
          notes: order.notes,
          totals: order.totals || {
            total: order.total,
            subtotal: order.total,
            fee: 0,
          },
          payments: order.payment_info || order.payments || [],
        },
      };
      await this._send(payload);
      notify("success", "Imprimindo", "Relatório de conferência enviado.");
    } catch (e) {
      console.error(e);
      notify("error", "Erro", "Falha na impressão.");
    }
  },

  async printCashClosing(closingData) {
    // ... (código existente mantido) ...
    try {
      const payload = {
        type: "closing",
        station: "frente",
        data: {
          operator: closingData.operator,
          opened_at: closingData.opened_at,
          summary: closingData.summary,
          methods: closingData.methods_breakdown,
          conference: closingData.conference,
          notes: closingData.notes,
        },
      };
      await this._send(payload);
      notify("success", "Imprimindo", "Relatório de fechamento enviado.");
    } catch (e) {
      console.error(e);
      notify("error", "Erro", "Falha ao imprimir fechamento.");
    }
  },

  async _send(body) {
    const res = await api.post("/print/add", body);
    if (!res.data.success) throw new Error("Erro na API de impressão");
    return res.data;
  },
};
