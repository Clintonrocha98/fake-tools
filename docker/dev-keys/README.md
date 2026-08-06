# Chaves de desenvolvimento

Estas quatro chaves secp256k1 (dois pares) são PÚBLICAS por definição: vivem
commitadas neste repositório privado e nunca representam nenhuma credencial
real do StarkBank. NUNCA usar fora de um ambiente de dev/local — o fake nunca
vê produção, e reusar qualquer uma destas chaves fora daqui quebra a garantia
de que "chave commitada = chave descartável".

- `client-dev.pem` / `client-dev.pub.pem` — o consumidor assina requests com a
  privada; o fake verifica com a pública.
- `webhook-dev.pem` / `webhook-dev.pub.pem` — o fake assina webhooks com a
  privada; o consumidor verifica com a pública.

## Como foram geradas

```bash
openssl ecparam -name secp256k1 -genkey -noout -out client-dev.pem
openssl ec -in client-dev.pem -pubout -out client-dev.pub.pem

openssl ecparam -name secp256k1 -genkey -noout -out webhook-dev.pem
openssl ec -in webhook-dev.pem -pubout -out webhook-dev.pub.pem
```
