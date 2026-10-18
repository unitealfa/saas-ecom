<flux:heading level="2">Informations professionnelles</flux:heading>
<flux:text>Ces champs sont facultatifs. Les informations renseignées sont contrôlées avant approbation. Les exemples sont illustratifs : saisissez les informations réelles du commerçant.</flux:text>
@foreach ([
    'legal_form' => ['Forme juridique', 'Le statut sous lequel vous exercez votre activité.', 'Ex. : entreprise individuelle, EURL ou SARL'],
    'activity_nature' => ['Nature de l’activité', 'Décrivez les produits vendus ou votre activité.', 'Ex. : vente de vêtements en ligne'],
    'nif' => ['NIF', 'Numéro d’identification fiscale : recopiez celui de votre document fiscal, en conservant les zéros au début.', 'Ex. fictif : 000123456789012'],
    'nis' => ['NIS', 'Numéro d’identification statistique : recopiez celui de votre document statistique, en conservant les zéros au début.', 'Ex. fictif : 000987654321098'],
    'registration_number' => ['Registre de commerce', 'Recopiez le numéro inscrit sur le registre de commerce.', 'Ex. fictif : RC-EXEMPLE-001'],
    'artisan_card_number' => ['Carte artisan', 'Le numéro de carte professionnelle d’artisan, si vous en possédez une.', 'Ex. fictif : ART-EXEMPLE-001'],
] as $field => [$label, $description, $placeholder])
    <flux:input :name="$field" :label="$label.' (facultatif)'" :description="$description" :placeholder="$placeholder" :value="old($field, ($profile ?? null)?->getAttribute($field))" />
@endforeach
<flux:textarea name="legal_address" label="Adresse professionnelle (facultatif)" description="Numéro, rue, commune, wilaya ou région et pays." placeholder="Ex. fictif : 12 rue des Oliviers, Bab Ezzouar, Alger, Algérie">{{ old('legal_address', ($profile ?? null)?->getAttribute('legal_address')) }}</flux:textarea>
<flux:input type="number" name="share_capital" label="Capital social (facultatif)" description="Le montant du capital de la société, si applicable." placeholder="Ex. : 100000.00" min="0" step="0.01" :value="old('share_capital', ($profile ?? null)?->getAttribute('share_capital'))" />
