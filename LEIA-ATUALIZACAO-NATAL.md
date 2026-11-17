# Atualização: Tema de Natal (Naffity)

Este arquivo e o ZIP servem apenas como referência. **Não envie os dois para a Hostinger.**

## Arquivos que SUBSTITUEM os existentes
- `index.html`: cardápio. Carrega o tema no `<head>` e inclui os ornamentos e a frase, ocultos no tema padrão.
- `admin.html`: nova aba **Aparência** com o controle "Tema de Natal".
- `api/index.php`: nova rota `theme`, com uma única linha de roteamento adicionada.

## Arquivos NOVOS
- `api/routes/theme.php`: leitura e gravação do tema. A gravação usa POST e exige login de admin.
- `api/data/.htaccess`: bloqueia o acesso público à pasta de configuração.
- `css/christmas.css`: estilos do Natal, todos isolados por `html[data-theme="natal"]`.
- `js/christmas.js`: animações e interações do Natal. Só inicializa com o tema ativo.
- `js/admin-theme.js`: lógica do botão no admin.

## Envio (Gerenciador de Arquivos da Hostinger)
1. Faça um backup baixando `index.html`, `admin.html` e `api/index.php` do servidor.
2. Envie `naffity-tema-natal.zip` para `public_html` e use **Extrair** para essa mesma pasta. Confirme a substituição.
3. Exclua o `.zip` do servidor.
4. Nenhum SQL é necessário. Não é preciso mudar permissões: a pasta `api/data` usa o padrão 755.

## Arquivo de estado gerado
`api/data/theme.json` é criado automaticamente na primeira vez que o tema é alterado pelo admin. Ele não existe no ZIP.
Em futuras atualizações, **não apague nem sobrescreva** `api/data/theme.json`. Se ele faltar ou estiver inválido, o cardápio usa o tema padrão.
`api/data/theme.lock` também é criado automaticamente (arquivo vazio de bloqueio).

## Como ativar e desativar
Admin → aba **Aparência** → **Ativar tema de Natal** / **Voltar ao tema padrão**.
A mudança vale para todos os visitantes ao abrir ou atualizar o cardápio.

## Reversão
- Rápida: no admin, clique em "Voltar ao tema padrão".
- Completa: restaure o backup de `index.html`, `admin.html` e `api/index.php`. Depois apague `api/routes/theme.php`, a pasta `api/data`, `css/christmas.css`, `js/christmas.js` e `js/admin-theme.js`.
