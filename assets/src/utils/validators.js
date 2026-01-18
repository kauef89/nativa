/**
 * Utilitários de Validação e Formatação
 */

// Remove tudo que não é dígito
export const cleanDigits = (str) => (str ? str.replace(/\D/g, "") : "");

// Valida CPF (Algoritmo Módulo 11) + Bypass de Teste
export const isValidCPF = (cpf) => {
  const clean = cleanDigits(cpf);

  // 1. Bypass de Teste (Regra de Negócio: Economia de API em Dev)
  if (clean === "00000000000") return "TEST_BYPASS";

  // 2. Validação Básica (Tamanho e Repetição)
  if (clean.length !== 11 || /^(\d)\1+$/.test(clean)) return false;

  // 3. Cálculo Matemático
  let soma = 0;
  let resto;

  for (let i = 1; i <= 9; i++)
    soma = soma + parseInt(clean.substring(i - 1, i)) * (11 - i);

  resto = (soma * 10) % 11;
  if (resto === 10 || resto === 11) resto = 0;
  if (resto !== parseInt(clean.substring(9, 10))) return false;

  soma = 0;
  for (let i = 1; i <= 10; i++)
    soma = soma + parseInt(clean.substring(i - 1, i)) * (12 - i);

  resto = (soma * 10) % 11;
  if (resto === 10 || resto === 11) resto = 0;
  if (resto !== parseInt(clean.substring(10, 11))) return false;

  return true; // CPF Válido matematicamente
};
