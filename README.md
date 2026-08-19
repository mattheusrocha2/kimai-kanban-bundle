# KanbanBundle — Kanban & Listas de Tarefas para o Kimai

Plugin (bundle Symfony) para o Kimai que adiciona, por projeto:

- **Quadro Kanban** estilo Trello: listas/colunas arrastáveis entre si (reordenar as próprias colunas) e cards arrastáveis entre colunas ou reordenados dentro da mesma coluna.
- **Lista de tarefas** (visão em tabela) com os mesmos dados.
- Cada tarefa tem: título, descrição, checklist ("sublista" de itens), data de início, prazo de entrega, data de conclusão.
- **Cor por lista/coluna** (paleta estilo Trello, ex.: uma coluna "Urgente" toda vermelha) — cabeçalho colorido com contraste de texto calculado automaticamente.
- Botão **Iniciar/Pausar** em cada tarefa, que cria/encerra um **Timesheet real do Kimai** (vinculado a uma Activity dedicada "Kanban: <projeto>"), então o tempo trabalhado aparece nos relatórios, exportações e faturamento nativos do Kimai.
- **Lançamento manual de horário** por tarefa (data + hora início + hora fim) para quem esqueceu de apertar "Iniciar" — cria um Timesheet já encerrado, soma no tempo total, e pode ser listado/excluído.
- Visual usa as variáveis de tema do Tabler (`--tblr-*`), então acompanha automaticamente o modo claro/escuro do Kimai. O modal de tarefa é autocontido (não depende de jQuery/Bootstrap JS globais, que o Kimai atual não expõe).

✅ **Testado de ponta a ponta** (ago/2026) num Kimai 2.65.0 local via Docker: criação de board/listas/tarefas, iniciar/pausar timer (com verificação direta na tabela `kimai2_timesheet` e na tela nativa "My times"), drag & drop entre listas, checklist, cor do card, exclusão de lista/tarefa. Ver seção "Testar localmente" abaixo para reproduzir.

## Estrutura

Segue a convenção de plugin do Kimai (bundle Symfony em `var/plugins/`):

```
KanbanBundle/
├── Command/              KanbanInstallCommand (bin/console kimai:bundle:kanban:install)
├── Controller/           BoardController (páginas) e TaskController (AJAX)
├── DependencyInjection/  KanbanExtension (registra services.yaml e permissões)
├── Entity/               Board, TaskList, Task, ChecklistItem
├── Repository/
├── Service/              TaskTimeTrackingService (start/pause -> Timesheet)
├── EventSubscriber/      MenuSubscriber (item "Kanban" no menu principal)
├── Twig/                 filtro kanban_duration (segundos -> HH:MM)
├── Migrations/           cria/evoluem as tabelas kimai_kanban_* (+ migrations.yaml próprio)
├── Resources/config/     services.yaml, routes.yaml
├── Resources/views/      templates Twig (board, list, modal de tarefa)
├── Resources/public/     css/js do quadro (drag & drop nativo, sem libs externas)
├── Resources/translations/ pt_BR e en
├── KanbanBundle.php
└── composer.json
```

## Testar localmente antes de instalar no Kimai do Coolify

Há um `docker-compose.yml` e `.env` na raiz deste repositório (um nível acima de `KanbanBundle/`) que sobem um Kimai 2.x + MySQL locais, com o bundle montado via bind mount (edições no código refletem direto, sem rebuild de imagem):

```bash
cd plugin-kimai   # a pasta que contém docker-compose.yml e KanbanBundle/
docker compose up -d
```

Aguarde ~1 min pela primeira inicialização (cria banco, usuário admin, etc. — login/senha ficam em `.env`, `ADMIN_EMAIL`/`ADMIN_PASSWORD`). Depois:

```bash
# instala tabelas do plugin + assets (CSS/JS) dentro do container
docker exec plugin-kimai-kimai-1 /opt/kimai/bin/console kimai:bundle:kanban:install -n
```

> ⚠️ `docker exec` não abre num shell com diretório de trabalho `/opt/kimai`, então `bin/console` (caminho relativo) não é encontrado — sempre use o caminho completo `/opt/kimai/bin/console` nos comandos `docker exec` abaixo.

Acesse `http://localhost:8001`, crie um Customer/Project de teste e abra **Kanban** no menu lateral.

**Sempre que editar um arquivo do plugin** (template Twig, PHP, CSS, JS), rode de novo dentro do container:

```bash
docker exec plugin-kimai-kimai-1 rm -rf /opt/kimai/var/cache/prod
docker exec plugin-kimai-kimai-1 /opt/kimai/bin/console cache:clear --env=prod   # pega mudanças em PHP/Twig
docker exec plugin-kimai-kimai-1 /opt/kimai/bin/console assets:install          # pega mudanças em CSS/JS (são copiados, não symlink)
```

O container roda como `www-data` e ajusta a posse de `var/` no boot — se o Docker "tomar posse" dos arquivos do host e o Claude/seu editor não conseguir mais salvar, rode:

```bash
docker exec -u root plugin-kimai-kimai-1 chown -R $(id -u):$(id -g) /opt/kimai/var/plugins/KanbanBundle
```

Para encerrar mantendo os dados: `docker compose down`. Para apagar tudo (banco incluso): `docker compose down -v`.

## Instalação automática no Coolify (recomendado)

Se o seu Kimai no Coolify foi instalado pela opção de app pronto (um clique, sem repositório git por trás), veja **[`deploy/README.md`](deploy/README.md)** — ele explica como colar um `docker-compose.yml` que baixa e instala este plugin sozinho a cada deploy, direto deste repositório GitHub, sem precisar copiar arquivo nenhum manualmente.

## Instalação manual no Kimai (Coolify)

1. Copie a pasta inteira `KanbanBundle` para dentro do container/volume do Kimai, em `var/plugins/KanbanBundle`.
   - No Coolify, isso normalmente significa montar/copiar para o volume persistente que aponta para `/opt/kimai/var/plugins/` (confirme o caminho do volume do seu deploy).
2. Acesse o container e rode:
   ```bash
   cd /opt/kimai   # ajuste para o caminho real da instalação
   bin/console cache:clear --env=prod
   bin/console kimai:bundle:kanban:install -n
   bin/console cache:clear --env=prod
   ```
   O comando único `kimai:bundle:kanban:install` já roda as migrations do plugin **e** publica CSS/JS (`assets:install`) — não é necessário chamar `doctrine:migrations:migrate` nem `assets:install` manualmente.
3. Reinicie o serviço do Kimai no Coolify para garantir que o bundle seja carregado.
4. Faça login como Super Admin, vá em **Administração > Papéis** e confira as novas permissões `view_kanban`, `create_kanban`, `edit_kanban`, `delete_kanban` — atribua aos papéis desejados (por padrão já liberadas para ROLE_USER/ROLE_TEAMLEAD/ROLE_ADMIN, e tudo para ROLE_SUPER_ADMIN).
5. O item **Kanban** aparece no menu principal. Escolha um projeto — o board é criado automaticamente na primeira visita, com 3 listas padrão ("A Fazer", "Em Andamento", "Concluído") e uma Activity dedicada para o rastreamento de tempo.

## Limitações conhecidas / próximos passos

- A listagem de projetos do plugin (`/kanban/`) mostra todos os projetos visíveis do sistema; ainda não filtra pela ACL fina de time/projeto do Kimai (apenas pela permissão geral `view_kanban`). Para produção multi-time, vale reforçar isso com `App\Repository\ProjectRepository` + verificação de acesso por projeto.
- Um usuário só pode ter **um** timesheet em andamento por vez (regra do próprio Kimai) — se ele já estiver com o cronômetro rodando em outro lugar do Kimai, o botão "Iniciar" retorna erro pedindo para pausar antes.
- As requisições AJAX não usam token CSRF dedicado (seguem a sessão autenticada); se sua instância exigir CSRF em todas as rotas POST, adicione validação com `IsCsrfTokenValid` nos controllers.
- Diálogos nativos do navegador (`alert`/`confirm`/`prompt`) foram evitados de propósito (travam a página) — exclusões usam um botão "clique de novo para confirmar" (4s de janela) e erros aparecem como um toast no canto da tela.
