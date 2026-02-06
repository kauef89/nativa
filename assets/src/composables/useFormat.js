// src/composables/useFormat.js

export function useFormat() {
  // Utilitário: Remove tudo que não for número
  const cleanDigits = (str) => {
    return str ? str.toString().replace(/\D/g, "") : "";
  };

  // Separador de Milhar para quantidades (1000 -> 1.000)
  const formatThousand = (value) => {
    const val = parseFloat(value);
    if (isNaN(val)) return "0";
    return val.toLocaleString("pt-BR");
  };

  // Dinheiro: 1250.5 -> 1.250,50
  const formatMoney = (value) => {
    const val = parseFloat(value);
    if (isNaN(val)) return "0,00";
    return val.toLocaleString("pt-BR", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  };

  // Moeda: 10.5 -> R$ 10,50
  const formatCurrency = (value) => {
    return "R$ " + formatMoney(value);
  };

  // Abrevia Nome: "Ana Flávia dos Santos" -> "Ana S."
  const abbreviateName = (fullName) => {
    if (!fullName) return "";
    const parts = fullName.trim().split(/\s+/);
    if (parts.length === 1) return parts[0]; // Só tem um nome

    const firstName = parts[0];
    const lastName = parts[parts.length - 1];

    // Capitaliza a primeira letra de cada (segurança estética)
    const firstCap =
      firstName.charAt(0).toUpperCase() + firstName.slice(1).toLowerCase();
    const lastCap = lastName.charAt(0).toUpperCase();

    return `${firstCap} ${lastCap}.`;
  };

  // Data: YYYY-MM-DD HH:MM:SS -> DD/MM/YYYY HH:MM
  const formatDate = (isoString, includeTime = false) => {
    if (!isoString) return "--";

    const date = new Date(isoString);
    if (isNaN(date.getTime())) return isoString;

    const options = { day: "2-digit", month: "2-digit", year: "numeric" };
    if (includeTime) {
      options.hour = "2-digit";
      options.minute = "2-digit";
    }
    return date.toLocaleDateString("pt-BR", options);
  };

  // Telefone: (47) 99999-8888 ou (47) 3448-0000
  const formatPhone = (phone) => {
    const clean = cleanDigits(phone);
    if (!clean) return "";

    // Celular (11 dígitos)
    if (clean.length === 11) {
      return clean.replace(/^(\d{2})(\d{5})(\d{4})/, "($1) $2-$3");
    }
    // Fixo (10 dígitos)
    if (clean.length === 10) {
      return clean.replace(/^(\d{2})(\d{4})(\d{4})/, "($1) $2-$3");
    }
    // Parcial (para inputs)
    if (clean.length > 2) {
      return clean.replace(/^(\d{2})/, "($1) ");
    }
    return phone;
  };

  // CPF: 12345678900 -> 123.456.789-00
  const formatCPF = (cpf) => {
    const clean = cleanDigits(cpf);
    if (!clean) return "";

    if (clean.length <= 11) {
      return clean.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
    }
    // Fallback para CNPJ
    return clean.replace(
      /(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/,
      "$1.$2.$3/$4-$5",
    );
  };

  return {
    cleanDigits,
    formatThousand, // Novo
    formatMoney,
    formatCurrency,
    abbreviateName, // Novo
    formatDate,
    formatPhone,
    formatCPF,
  };
}
