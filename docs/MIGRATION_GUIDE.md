# Guia de Migração - Sistema NLP Robusto

## Resumo das Mudanças

Este documento guia a implementação gradual do novo sistema NLP sem quebrar o código existente.

## Fase 1: Setup (30 min)

### 1.1 Copiar arquivos novos

```bash
# Python microservice
cp -r ml_service/ ml_service_new/

# Laravel services
cp app/Services/IntentClassifierService_Enhanced.php app/Services/
cp app/Services/FallbackIntentClassifier.php app/Services/

# Config
cp config/nlp_service.php config/

# Documentação
cp docs/NLP_MICROSERVICE.md docs/
```

### 1.2 Instalar dependências Python

```bash
cd ml_service
python -m venv venv
source venv/bin/activate

pip install -r requirements.txt
python -m spacy download en_core_web_sm pt_core_news_sm es_core_news_sm
```

### 1.3 Configurar Laravel

```bash
# Adicionar ao .env
NLP_SERVICE_URL=http://nlp_microservice:8001
NLP_SERVICE_TIMEOUT=10
NLP_CACHE_EXPIRY=3600
NLP_FALLBACK_ENABLED=true
```

## Fase 2: Testing Local (45 min)

### 2.1 Testar microserviço isolado

```bash
cd ml_service
python test_microservice.py
```

Esperado: todos os testes passem ✓

### 2.2 Iniciar microserviço manualmente

```bash
cd ml_service
python main.py
```

Acesso: `http://localhost:8001/docs` (Swagger UI)

### 2.3 Testar com Docker

```bash
# Build image Python
docker-compose build nlp-service

# Ou apenas para teste rápido
docker run -p 8001:8001 -v $(pwd)/ml_service:/app python:3.11 \
  bash -c "cd /app && pip install -r requirements.txt && python main.py"
```

## Fase 3: Integração Gradual (variável)

### 3.1 Criar nova classe com alias (SEM substituir original)

```php
// app/Services/IntentClassifierServiceV2.php
class IntentClassifierServiceV2 extends IntentClassifierService_Enhanced {
    // Compatible with existing codebase
}
```

### 3.2 Atualizar apenas um endpoint da API

Exemplo: `/api/chatbot/ask`

```php
// routes/api.php
Route::post('/chatbot/ask', function(Request $request) {
    $service = new IntentClassifierServiceV2(); // Novo
    // ...
});
```

### 3.3 A/B Testing (opcional)

```php
// Apenas 10% do tráfego para novo serviço
if (rand(1, 10) === 1) {
    $classifier = new IntentClassifierServiceV2();
} else {
    $classifier = new IntentClassifierService(); // Original
}
```

### 3.4 Log comparativo

```php
$newResult = (new IntentClassifierServiceV2())->classifyIntention($question);
$oldResult = (new IntentClassifierService())->classifyIntention($question);

Log::info("Intent Comparison", [
    'question' => $question,
    'new' => $newResult,
    'old' => $oldResult,
    'match' => $newResult === $oldResult ? 'yes' : 'no'
]);
```

## Fase 4: Substituição Completa (opcional)

Quando confiante nos resultados:

### 4.1 Renomear classe

```bash
# Backup original
mv app/Services/IntentClassifierService.php \
   app/Services/IntentClassifierService_Legacy.php

# Ativar novo
mv app/Services/IntentClassifierService_Enhanced.php \
   app/Services/IntentClassifierService.php
```

### 4.2 Testar suite completa

```bash
php artisan test
```

### 4.3 Deploy

```bash
docker-compose up -d
# ou Kubernetes, etc.
```

## Rollback Plan

Se algo der errado:

### Opção 1: Voltar ao original (10 min)

```bash
mv app/Services/IntentClassifierService.php app/Services/IntentClassifierService_V2_Rollback.php
mv app/Services/IntentClassifierService_Legacy.php app/Services/IntentClassifierService.php
```

Restart: `php artisan config:cache`

### Opção 2: Usar fallback automático

O novo sistema já detecta falhas e volta para regras locais. Nenhuma ação necessária!

## Validação

### Testes unitários

```php
// tests/Unit/IntentClassifierTest.php
public function test_actor_question() {
    $classifier = new IntentClassifierServiceV2();
    $result = $classifier->classifyIntention("What movies has Tom Cruise starred in?");
    $this->assertEquals('actor', $result);
}
```

### Testes de integração

```bash
chmod +x ml_service/test_microservice.py
python ml_service/test_microservice.py
```

### Teste de carga

```bash
# Simular 100 requests em paralelo
ab -n 100 -c 10 http://localhost:8001/health
```

## Monitoramento

### 1. Health checks

```php
// Add to scheduler (app/Console/Kernel.php)
$schedule->call(function () {
    $classifier = new IntentClassifierServiceV2();
    if (!$classifier->isServiceHealthy()) {
        Notification::send(auth()->user(), new AlertNotification());
    }
})->everyFiveMinutes();
```

### 2. Logging de falhas

```php
Log::channel('nlp_service')->info("Intent classified", [
    'intent' => $intent,
    'confidence' => $confidence,
    'service' => 'nlp-microservice',
    'source' => 'v2'
]);
```

### 3. Métricas

```php
// Log performance
$startTime = microtime(true);
$result = $classifier->classifyIntention($question);
$duration = microtime(true) - $startTime;

Log::info("Performance", ['duration_ms' => $duration * 1000]);
```

## Problemas Comuns

### Problema: "Connection refused" 

**Solução:**
```bash
# Verificar se microservice está rodando
curl http://localhost:8001/health

# Se não, iniciar:
docker-compose up nlp-service -d
```

### Problema: Timeout nas requests

**Solução:**
```env
# Aumentar timeout
NLP_SERVICE_TIMEOUT=30
```

### Problema: Modelos não sincronizam

**Solução:**
```bash
# Forçar re-download
cd ml_service
rm -rf ~/.cache/huggingface/
python main.py
```

## Próximos Passos

1. ✓ Setup local - concluído
2. ✓ Testes isolados - concluído
3. → Integração gradual - em progresso
4. → Deploy em staging
5. → Deploy em produção
6. → Monitoramento contínuo
7. → Coleta de feedback
8. → Fine-tuning com dados reais

## Suporte

Problemas?

- Logs: `docker logs nlp_microservice`
- Tests: `python ml_service/test_microservice.py`
- Docs: [NLP_MICROSERVICE.md](NLP_MICROSERVICE.md)
- Issues: Check existing tests

Sucesso! 🚀
