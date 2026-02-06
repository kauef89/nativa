import { reactive } from "vue";
import { io } from "socket.io-client";

export const socketState = reactive({
  connected: false,      
  bridgeOnline: false,   
  // printers: {}, <--- REMOVIDO
  lastHeartbeat: null
});

const SOCKET_URL = "https://socket.pastelarianativa.com.br"; 
const STORE_ID = "loja_1"; 

const socket = io(SOCKET_URL, {
  query: { store: STORE_ID, role: 'frontend' },
  transports: ["websocket"],
  autoConnect: true
});

socket.on("connect", () => {
  console.log("🔌 [FRONTEND] Socket Conectado!");
  socketState.connected = true;
  socket.emit("check-bridge-status");
});

socket.on("disconnect", () => {
  console.warn("🔌 [FRONTEND] Socket Desconectado.");
  socketState.connected = false;
  socketState.bridgeOnline = false; 
});

socket.on("bridge-status-change", (data) => {
  console.log("🖨️ [BRIDGE] Status:", data.status);
  socketState.bridgeOnline = (data.status === 'online');
  socketState.lastHeartbeat = new Date();
});

// socket.on("printer-status-update") <--- REMOVIDO

export default socket;