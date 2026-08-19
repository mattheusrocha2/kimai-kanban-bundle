# Deploy automático no Coolify

Este arquivo mostra como fazer o Coolify baixar e instalar este plugin sozinho a cada deploy do Kimai, sem precisar copiar arquivos manualmente pro servidor.

## Como funciona

O `docker-compose.coolify.yml` adiciona dois serviços "de uma execução só" (`restart: 'no'`) ao redor do seu Kimai já existente:

1. **`kanban-plugin-sync`** — baixa a última versão do plugin direto deste repositório GitHub (`main` branch) para um volume compartilhado, antes do Kimai subir.
2. **`kanban-plugin-install`** — depois que o Kimai já está saudável (schema principal pronto), roda `kimai:bundle:kanban:install` para criar as tabelas do plugin e publicar o CSS/JS.

Os dois usam a mesma imagem oficial `kimai/kimai2:apache` — nenhuma imagem customizada é necessária, então funciona com a instalação "um clique" do Coolify (sem repositório git por trás do compose).

Isso roda em **todo deploy/restart**, então:
- Atualizações no plugin (push pra branch `main` deste repo) são aplicadas automaticamente no próximo redeploy do Kimai no Coolify.
- Migrations e assets são reaplicados de forma idempotente (seguro rodar de novo, não duplica nada).

## Como aplicar

1. Confirme que o repositório está público: https://github.com/mattheusrocha2/kimai-kanban-bundle
2. No Coolify, abra o recurso do Kimai e vá na aba onde fica o `docker-compose.yml`.
3. Compare com o arquivo `docker-compose.coolify.yml` deste diretório — as mudanças em relação ao seu compose original são:
   - Novo serviço `kanban-plugin-sync`.
   - Novo serviço `kanban-plugin-install`.
   - No serviço `kimai`: dois volumes novos (`kimai-plugins` e `kimai-bundles`) e uma nova dependência (`kanban-plugin-sync` completo).
   - No bloco `volumes:` do final: duas entradas novas (`kimai-plugins`, `kimai-bundles`).
4. Cole essas adições no seu compose real no Coolify (mantendo suas variáveis `${SERVICE_...}` como já estão).
5. Faça o deploy. Acompanhe os logs — o `kanban-plugin-install` deve terminar com `Congratulations! Plugin was successful installed: KanbanBundle`.
6. Acesse o Kimai, confirme que **Kanban** aparece no menu lateral.

## Testado localmente antes de entregar

Essa configuração foi validada do zero (banco vazio, sem nenhum estado prévio) num ambiente Docker local espelhando exatamente este compose, incluindo:
- Download automático do plugin via `curl` (sem precisar de `git` na imagem).
- Ordem de dependências (sync → kimai saudável → install) sem condição de corrida.
- Criação das 3 migrations e publicação de CSS/JS visíveis pelo serviço real do Kimai (não só no container de instalação).
- Menu "Kanban" aparecendo e a página carregando com o tema correto.

## Se algo der errado

Veja os logs do serviço `kanban-plugin-install` no Coolify. As causas mais comuns:
- **"There are no commands defined in the kanban namespace"** — o `cache:clear` não rodou antes do install (confira se o `command:` do serviço não foi alterado).
- **"Allowed memory size exhausted"** — falta o `-d memory_limit=512M` no comando (o `entrypoint.sh` original do Kimai normalmente ajusta isso, mas este serviço o contorna de propósito).
- **Erro de conexão com o banco** — confira se `kanban-plugin-install` está usando as mesmas variáveis `DATABASE_URL`/`APP_SECRET` do serviço `kimai`.
