# Deploy Notes - DigitalOcean Bagisto

## Problemas Identificados e Soluções Implementadas

### 1. Erro CoreConvention
**Problema**: `WebKul\Core\CoreConvention is not a valid convention class`

**Causa**: O erro ocorria durante `composer install --no-dev` porque:
- Dependências dev eram removidas, causando problemas no autoload
- O `package:discover` estava sendo executado antes da limpeza de cache
- Timeout em comandos longos no ambiente DigitalOcean

**Solução**:
- ✅ CoreConvention está implementado corretamente (estende ConcordDefault)
- ✅ Script de deploy otimizado com timeouts e tratamento de erros
- ✅ Ordem correta dos comandos: clear cache → package:discover → migrations
- ✅ Variáveis de ambiente completas adicionadas ao app.yaml

### 2. Variáveis de Ambiente
**Adicionadas ao app.yaml**:
- LOG_CHANNEL, LOG_LEVEL, BCRYPT_ROUNDS
- CACHE_STORE, FILESYSTEM_DISK, BROADCAST_CONNECTION
- RESPONSE_CACHE_ENABLED, MAIL_MAILER
- SESSION_LIFETIME
- Configurações de email padrão

### 3. Script de Deploy Otimizado
**Melhorias implementadas**:
- Timeouts para comandos longos (composer: 600s, package:discover: 180s)
- Tratamento robusto de erros com logs detalhados
- Comandos não-críticos podem falhar sem parar deploy (seed, storage:link)
- Permissões adequadas para produção
- Flags otimizadas para composer (--no-dev, --no-progress, --prefer-dist)

### 4. Comando de Deploy
```bash
php -f .do/deploy.php
```

### 5. Verificação Pós-Deploy
Após deploy bem-sucedido, verificar:
1. Application rodando na porta 8080
2. Conexão com banco PostgreSQL funcional
3. Logs não mostrando erros críticos
4. Health check respondendo na rota "/"

### 6. Troubleshooting
Se ainda houver problemas:

**Timeout no composer**:
- Aumentar timeout no app.yaml ou usar instância maior
- Verificar conexão de rede do DigitalOcean

**Erro de permissões**:
- Script já define permissões 755 para storage e cache
- Se persistir, verificar proprietário dos arquivos

**Erro no banco**:
- Verificar variável ${DB_PASSWORD} está definida no projeto DO
- Testar conexão PostgreSQL manualmente

**Package discovery still failing**:
- Executar manualmente: `php artisan package:discover --clear`
- Verificar se composer autoload está correto

### 7. Logs para Monitoramento
- LOG_LEVEL definido como "error" para produção
- LOG_CHANNEL como "stack" para melhor organização
- Logs disponíveis em storage/logs/