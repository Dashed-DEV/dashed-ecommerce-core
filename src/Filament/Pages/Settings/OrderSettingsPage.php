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
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Qubiqx\QcommerceCore\Classes\Locales;
use Qubiqx\QcommerceCore\Classes\Sites;
use Qubiqx\QcommerceCore\Models\Customsetting;
use Qubiqx\QcommerceCore\Models\User;

class OrderSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $navigationLabel = 'Bestelling instellingen';
    protected static ?string $navigationGroup = 'Overige';
    protected static ?string $title = 'Bestelling instellingen';

    protected static string $view = 'qcommerce-core::settings.pages.default-settings';

    public function mount(): void
    {
        $formData = [];
        $sites = Sites::getSites();
        $locales = Locales::getLocales();
        foreach ($sites as $site) {
            $formData["notification_invoice_emails_{$site['id']}"] = json_decode(Customsetting::get('notification_invoice_emails', $site['id'], '{}'));

        }

        $formData["order_index_show_other_statuses"] = Customsetting::get('order_index_show_other_statuses', null, true) ? true : false;

        foreach ($locales as $locale) {
            $formData["fulfillment_status_unhandled_enabled_{$locale['id']}"] = Customsetting::get('fulfillment_status_unhandled_enabled', null, false, $locale['id']) ? true : false;
            $formData["fulfillment_status_unhandled_email_subject_{$locale['id']}"] = Customsetting::get('fulfillment_status_unhandled_email_subject', null, null, $locale['id']);
            $formData["fulfillment_status_unhandled_email_content_{$locale['id']}"] = Customsetting::get('fulfillment_status_unhandled_email_content', null, null, $locale['id']);
            $formData["fulfillment_status_in_treatment_enabled_{$locale['id']}"] = Customsetting::get('fulfillment_status_in_treatment_enabled', null, false, $locale['id']) ? true : false;
            $formData["fulfillment_status_in_treatment_email_subject_{$locale['id']}"] = Customsetting::get('fulfillment_status_in_treatment_email_subject', null, null, $locale['id']);
            $formData["fulfillment_status_in_treatment_email_content_{$locale['id']}"] = Customsetting::get('fulfillment_status_in_treatment_email_content', null, null, $locale['id']);
            $formData["fulfillment_status_packed_enabled_{$locale['id']}"] = Customsetting::get('fulfillment_status_packed_enabled', null, false, $locale['id']) ? true : false;
            $formData["fulfillment_status_packed_email_subject_{$locale['id']}"] = Customsetting::get('fulfillment_status_packed_email_subject', null, null, $locale['id']);
            $formData["fulfillment_status_packed_email_content_{$locale['id']}"] = Customsetting::get('fulfillment_status_packed_email_content', null, null, $locale['id']);
            $formData["fulfillment_status_shipped_enabled_{$locale['id']}"] = Customsetting::get('fulfillment_status_shipped_enabled', null, false, $locale['id']) ? true : false;
            $formData["fulfillment_status_shipped_email_subject_{$locale['id']}"] = Customsetting::get('fulfillment_status_shipped_email_subject', null, null, $locale['id']);
            $formData["fulfillment_status_shipped_email_content_{$locale['id']}"] = Customsetting::get('fulfillment_status_shipped_email_content', null, null, $locale['id']);
            $formData["fulfillment_status_handled_enabled_{$locale['id']}"] = Customsetting::get('fulfillment_status_handled_enabled', null, false, $locale['id']) ? true : false;
            $formData["fulfillment_status_handled_email_subject_{$locale['id']}"] = Customsetting::get('fulfillment_status_handled_email_subject', null, null, $locale['id']);
            $formData["fulfillment_status_handled_email_content_{$locale['id']}"] = Customsetting::get('fulfillment_status_handled_email_content', null, null, $locale['id']);
        }

        $this->form->fill($formData);
    }

    protected function getFormSchema(): array
    {
        $sites = Sites::getSites();
        $locales = Locales::getLocales();
        $tabGroups = [];

        $schema = [
            Placeholder::make('label')
                ->label("Algemene instelling voor bestellingen"),
            Checkbox::make("order_index_show_other_statuses")
                ->label('Toon de extra statussen op het bestellingsoverzicht'),
        ];

        $tabGroups[] = Card::make()
            ->schema($schema)
            ->columns([
                'default' => 1,
                'lg' => 2,
            ]);

        $tabs = [];
        foreach ($sites as $site) {
            $schema = [
                Placeholder::make('label')
                    ->label("Notificaties voor bestellingen op {$site['name']}")
                    ->content('Stel extra opties in voor de notificaties.'),
                TagsInput::make("notification_invoice_emails_{$site['id']}")
                    ->suggestions(User::where('role', 'admin')->pluck('email')->toArray())
                    ->label('Emails om de bevestigingsmail van een bestelling naar te sturen')
                    ->placeholder('Voer een email in')
                    ->reactive()
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

        $tabs = [];
        foreach ($locales as $locale) {
            $schema = [
                Placeholder::make('label')
                    ->label("Fulfillment notificaties voor {$locale['name']}"),
                Checkbox::make("fulfillment_status_unhandled_enabled_{$locale['id']}")
                    ->label('Fulfillment status "Niet afgehandeld" actie')
                    ->reactive()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextInput::make("fulfillment_status_unhandled_email_subject_{$locale['id']}")
                    ->label('Fulfillment status "Niet afgehandeld" mail onderwerp')
                    ->hidden(fn($get) => !$get("fulfillment_status_unhandled_enabled_{$locale['id']}")),
                RichEditor::make("fulfillment_status_unhandled_email_content_{$locale['id']}")
                    ->label('Fulfillment status "Niet afgehandeld" mail inhoud')
                    ->fileAttachmentsDisk('qcommerce-uploads')
                    ->toolbarButtons([
                        'attachFiles',
                        'blockquote',
                        'bold',
                        'bulletList',
                        'codeBlock',
                        'h2',
                        'h3',
                        'h4',
                        'italic',
                        'link',
                        'orderedList',
                        'redo',
                        'strike',
                        'undo',
                    ])
                    ->hidden(fn($get) => !$get("fulfillment_status_unhandled_enabled_{$locale['id']}")),
                Checkbox::make("fulfillment_status_in_treatment_enabled_{$locale['id']}")
                    ->label('Fulfillment status "In behandeling" actie')
                    ->reactive()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextInput::make("fulfillment_status_in_treatment_email_subject_{$locale['id']}")
                    ->label('Fulfillment status "In behandeling" mail onderwerp')
                    ->hidden(fn($get) => !$get("fulfillment_status_in_treatment_enabled_{$locale['id']}")),
                RichEditor::make("fulfillment_status_in_treatment_email_content_{$locale['id']}")
                    ->label('Fulfillment status "In behandeling" mail inhoud')
                    ->fileAttachmentsDisk('qcommerce-uploads')
                    ->toolbarButtons([
                        'attachFiles',
                        'blockquote',
                        'bold',
                        'bulletList',
                        'codeBlock',
                        'h2',
                        'h3',
                        'h4',
                        'italic',
                        'link',
                        'orderedList',
                        'redo',
                        'strike',
                        'undo',
                    ])
                    ->hidden(fn($get) => !$get("fulfillment_status_in_treatment_enabled_{$locale['id']}")),
                Checkbox::make("fulfillment_status_packed_enabled_{$locale['id']}")
                    ->label('Fulfillment status "Ingepakt" actie')
                    ->reactive()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextInput::make("fulfillment_status_packed_email_subject_{$locale['id']}")
                    ->label('Fulfillment status "Ingepakt" mail onderwerp')
                    ->hidden(fn($get) => !$get("fulfillment_status_packed_enabled_{$locale['id']}")),
                RichEditor::make("fulfillment_status_packed_email_content_{$locale['id']}")
                    ->label('Fulfillment status "Ingepakt" mail inhoud')
                    ->fileAttachmentsDisk('qcommerce-uploads')
                    ->toolbarButtons([
                        'attachFiles',
                        'blockquote',
                        'bold',
                        'bulletList',
                        'codeBlock',
                        'h2',
                        'h3',
                        'h4',
                        'italic',
                        'link',
                        'orderedList',
                        'redo',
                        'strike',
                        'undo',
                    ])
                    ->hidden(fn($get) => !$get("fulfillment_status_packed_enabled_{$locale['id']}")),
                Checkbox::make("fulfillment_status_shipped_enabled_{$locale['id']}")
                    ->label('Fulfillment status "Verzonden" actie')
                    ->reactive()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextInput::make("fulfillment_status_shipped_email_subject_{$locale['id']}")
                    ->label('Fulfillment status "Verzonden" mail onderwerp')
                    ->hidden(fn($get) => !$get("fulfillment_status_shipped_enabled_{$locale['id']}")),
                RichEditor::make("fulfillment_status_shipped_email_content_{$locale['id']}")
                    ->label('Fulfillment status "Verzonden" mail inhoud')
                    ->fileAttachmentsDisk('qcommerce-uploads')
                    ->toolbarButtons([
                        'attachFiles',
                        'blockquote',
                        'bold',
                        'bulletList',
                        'codeBlock',
                        'h2',
                        'h3',
                        'h4',
                        'italic',
                        'link',
                        'orderedList',
                        'redo',
                        'strike',
                        'undo',
                    ])
                    ->hidden(fn($get) => !$get("fulfillment_status_shipped_enabled_{$locale['id']}")),
                Checkbox::make("fulfillment_status_handled_enabled_{$locale['id']}")
                    ->label('Fulfillment status "Afgehandeld" actie')
                    ->reactive()
                    ->columnSpan([
                        'default' => 1,
                        'lg' => 2,
                    ]),
                TextInput::make("fulfillment_status_handled_email_subject_{$locale['id']}")
                    ->label('Fulfillment status "Afgehandeld" mail onderwerp')
                    ->hidden(fn($get) => !$get("fulfillment_status_handled_enabled_{$locale['id']}")),
                RichEditor::make("fulfillment_status_handled_email_content_{$locale['id']}")
                    ->label('Fulfillment status "Afgehandeld" mail inhoud')
                    ->fileAttachmentsDisk('qcommerce-uploads')
                    ->toolbarButtons([
                        'attachFiles',
                        'blockquote',
                        'bold',
                        'bulletList',
                        'codeBlock',
                        'h2',
                        'h3',
                        'h4',
                        'italic',
                        'link',
                        'orderedList',
                        'redo',
                        'strike',
                        'undo',
                    ])
                    ->hidden(fn($get) => !$get("fulfillment_status_handled_enabled_{$locale['id']}")),
            ];

            $tabs[] = Tab::make($locale['id'])
                ->label(ucfirst($locale['name']))
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
        $locales = Locales::getLocales();
        $formState = $this->form->getState();

        foreach ($sites as $site) {
            $emails = $this->form->getState()["notification_invoice_emails_{$site['id']}"];
            foreach ($emails as $key => $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    unset($emails[$key]);
                }
            }
            Customsetting::set('notification_invoice_emails', $emails, $site['id']);
            $formState["notification_invoice_emails_{$site['id']}"] = $emails;
        }

        Customsetting::set('order_index_show_other_statuses', $this->form->getState()["order_index_show_other_statuses"]);

        foreach ($locales as $locale) {
            Customsetting::set('fulfillment_status_unhandled_enabled', $this->form->getState()["fulfillment_status_unhandled_enabled_{$locale['id']}"], null, $locale['id']);
            Customsetting::set('fulfillment_status_unhandled_email_subject', $this->form->getState()["fulfillment_status_unhandled_email_subject_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_unhandled_email_content', $this->form->getState()["fulfillment_status_unhandled_email_content_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_in_treatment_enabled', $this->form->getState()["fulfillment_status_in_treatment_enabled_{$locale['id']}"], null, $locale['id']);
            Customsetting::set('fulfillment_status_in_treatment_email_subject', $this->form->getState()["fulfillment_status_in_treatment_email_subject_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_in_treatment_email_content', $this->form->getState()["fulfillment_status_in_treatment_email_content_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_packed_enabled', $this->form->getState()["fulfillment_status_packed_enabled_{$locale['id']}"], null, $locale['id']);
            Customsetting::set('fulfillment_status_packed_email_subject', $this->form->getState()["fulfillment_status_packed_email_subject_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_packed_email_content', $this->form->getState()["fulfillment_status_packed_email_content_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_shipped_enabled', $this->form->getState()["fulfillment_status_shipped_enabled_{$locale['id']}"], null, $locale['id']);
            Customsetting::set('fulfillment_status_shipped_email_subject', $this->form->getState()["fulfillment_status_shipped_email_subject_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_shipped_email_content', $this->form->getState()["fulfillment_status_shipped_email_content_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_handled_enabled', $this->form->getState()["fulfillment_status_handled_enabled_{$locale['id']}"], null, $locale['id']);
            Customsetting::set('fulfillment_status_handled_email_subject', $this->form->getState()["fulfillment_status_handled_email_subject_{$locale['id']}"] ?? '', null, $locale['id']);
            Customsetting::set('fulfillment_status_handled_email_content', $this->form->getState()["fulfillment_status_handled_email_content_{$locale['id']}"] ?? '', null, $locale['id']);
        }
        $this->form->fill($formState);
        $this->notify('success', 'De bestellings instellingen zijn opgeslagen');
    }
}