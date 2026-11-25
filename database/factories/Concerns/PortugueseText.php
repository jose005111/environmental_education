<?php

namespace Database\Factories\Concerns;

trait PortugueseText
{
    private const TITLES = [
        'Como reduzir o lixo em casa',
        'Cuidados com a água da chuva',
        'Reciclagem no bairro',
        'Protegendo as árvores da praça',
        'Saneamento e saúde no bairro',
        'Compostagem de resíduos orgânicos',
        'Energia limpa na escola',
        'Observando a biodiversidade urbana',
        'Mata Atlântica: por que preservar',
        'Dicas para economizar água',
    ];

    private const SENTENCES = [
        'Pequenas atitudes diárias ajudam a proteger o meio ambiente.',
        'A separação correta do lixo facilita a reciclagem.',
        'A água da chuva pode ser aproveitada para regar as plantas.',
        'A participação da comunidade é essencial para cuidar do bairro.',
        'Árvores urbanas melhoram a qualidade do ar e o conforto térmico.',
        'Reduzir o consumo de plásticos diminui a quantidade de resíduos.',
        'A manutenção da drenagem evita alagamentos na época das chuvas.',
        'Observar as aves da região ajuda a acompanhar a biodiversidade local.',
    ];

    protected function ptTitle(): string
    {
        return fake()->randomElement(self::TITLES);
    }

    protected function ptSentence(): string
    {
        return fake()->randomElement(self::SENTENCES);
    }

    protected function ptParagraph(): string
    {
        return implode(' ', fake()->randomElements(self::SENTENCES, 3));
    }
}
