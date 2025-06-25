# 🧩 Molo Node

This is the official node implementation for the **UseMolo** decentralized torrent network.

UseMolo Nodes help distribute and seed content in a censorship-resistant and wallet-authenticated environment. By running a Molo Node, you contribute storage and bandwidth to the network, while staying in control of your node's identity and operations.

🔗 **Try Remote Torrenting:** [https://usemolo.com](https://usemolo.com)

---

## 🚀 Features

- ✅ Register securely with the UseMolo API using a wallet-based secret
- ✅ Docker-based setup for easy deployment and upgrades
- ✅ Transmission-powered torrent engine
- ✅ Periodic bandwidth and system reporting
- ✅ Minimal resource requirements (can run on VPS or home server)

---

## 📦 Requirements

- Linux or WSL environment
- Docker + Docker Compose
- `jq` (for JSON parsing in bash)
- Open ports for torrenting (default Transmission setup)
- 100GB or more storage

---

## 🛠 Installation

```bash
git clone https://github.com/justinngc/usemolo-node.git
cd usemolo-node
bash installer.sh
