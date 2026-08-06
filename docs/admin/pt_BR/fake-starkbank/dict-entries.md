---
title: DICT
icon: heroicon-o-key
order: 4
---

# DICT

O registro de chaves PIX deste fake — o único lugar onde o beneficiário de um cash-out existe, e de onde o preview de BR Code tira o taxId do recebedor que a aplicação consumidora confere antes do funding.

Duas chaves são semeadas em todo start: o beneficiário de referência e o recebedor do funding cross-fake. As duas vêm de configuração, então reseedar reescreve em vez de duplicar.

## Registrar uma chave

**Registrar chave** abre o formulário. Você informa a chave, o tipo, o titular (nome, CPF/CNPJ, pessoa física ou jurídica) e o banco fictício. Os blobs opacos de agência e conta **não** são digitados: são derivados da chave, exatamente como o provedor os emite.

Registrar a mesma chave de novo corrige o titular — um registro DICT é estado declarativo, nunca um segundo dono para a mesma chave.
