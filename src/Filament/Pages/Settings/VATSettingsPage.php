<?php

namespace Qubiqx\QcommerceEcommerceCore\Filament\Pages\Settings;

use Filament\Forms\Components\Card;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Qubiqx\QcommerceCore\Classes\Locales;
use Qubiqx\QcommerceCore\Classes\Sites;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceCore\Models\User;

class VATSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-tax';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'BTW instellingen';
    protected static ?string $navigationGroup = 'Overige';
    protected static ?string $title = 'BTW instellingen';

    protected static string $view = 'qcommerce-core::settings.pages.default-settings';

    public function mount(): void
    {
        $formData = [];
        $sites = Sites::getSites();
        foreach ($sites as $site) {
            $formData["taxes_prices_include_taxes_{$site['id']}"] = json_decode(Customsetting::get('taxes_prices_include_taxes', $site['id'], 1));
        }

        $this->form->fill($formData);
    }

    protected function getFormSchema(): array
    {
        $sites = Sites::getSites();
        $tabGroups = [];

        $tabs = [];
        foreach ($sites as $site) {
            $schema = [
                Placeholder::make('label')
                    ->label("BTW instellingen voor {$site['name']}"),
                Toggle::make("taxes_prices_include_taxes_{$site['id']}")
                    ->label('Alle prijzen zijn inclusief belasting')
                    ->helperText('Indien dit aangevinkt staat wordt de opgegeven prijs bij een product gerekend als inclusief BTW. Indien dit staat uitgeschakeld wordt de BTW over de producten pas bij de checkout berekend.')
                    ->required(),
            ];

            $tabs[] = Tab::make($site['id'])
                ->label(ucfirst($site['name']))
                ->schema($schema)
                ->columns([
                    'default' => 1,
                    'lg' => 2,
                ]);
        }
        $tabGroups[] = Tabs::make('Sites')
            ->tabs($tabs);

        return $tabGroups;
    }

    public function submit()
    {
        $sites = Sites::getSites();

        foreach ($sites as $site) {
            Customsetting::set('taxes_prices_include_taxes', $this->form->getState()["taxes_prices_include_taxes_{$site['id']}"], $site['id']);
        }
        
        $this->notify('success', 'De BTW instellingen zijn opgeslagen');
    }
}