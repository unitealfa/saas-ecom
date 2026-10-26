<div class="flex flex-col gap-3 break-all py-3">
    <flux:text>Chez le fournisseur DNS, configurez ces enregistrements :</flux:text>
    <p><strong>Pointage</strong> : CNAME vers {{ config('tenancy.saas_base_domain') }}, ou A/AAAA vers les mêmes IP publiques que cette base SaaS.</p>
    <p><strong>TXT</strong> : {{ \App\Services\Central\Domains\DomainDnsInspector::TXT_PREFIX.$domain->domain }}</p>
    <p>Valeur TXT : <code>{{ $verificationToken }}</code></p>
    <flux:text>Les domaines racine avec A/AAAA ou ALIAS/ANAME sont acceptés lorsque leurs IP publiques finales correspondent à celles de la base SaaS. Un proxy masquant ces IP doit être configuré pour permettre ce contrôle. Attendez la propagation DNS puis lancez « Vérifier ».</flux:text>
    <flux:text>Le contrôle DNS et le contrôle HTTPS sont distincts. Le serveur web doit accepter ce domaine et présenter un certificat HTTPS valide ; ce module contrôle le certificat existant, sans le créer.</flux:text>
</div>
