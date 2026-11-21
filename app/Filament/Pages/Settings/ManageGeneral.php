<?php

namespace App\Filament\Pages\Settings;

use App\Domain\Settings\Branding;
use App\Domain\Settings\GeneralSettings;
use App\Domain\Settings\Rules\LogoImage;
use BackedEnum;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ManageGeneral extends BaseSettingsPage
{
    /** Logo stored before this save, removed from disk once it is replaced or removed. */
    private ?string $previousLogo = null;

    protected static string $settings = GeneralSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'General';

    protected static ?string $slug = 'settings/general';

    protected static ?string $title = 'General';

    protected static ?int $navigationSort = 1;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Organisation')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('site_name')->required()->maxLength(60),
                    TextInput::make('organization_name')->required()->maxLength(150),
                    TextInput::make('tagline')->maxLength(150)->columnSpanFull(),
                    Select::make('timezone')->options(fn () => array_combine(timezone_identifiers_list(), timezone_identifiers_list()))->searchable()->required(),
                ]),
            Section::make('Application logo')
                ->description('Shown on the sign-in screen, in the staff portal and in the admin console header. Without a logo, the built-in SSO mark is used.')
                ->icon(Heroicon::OutlinedPhoto)
                ->columnSpanFull()
                ->schema([
                    FileUpload::make('logo_path')
                        ->hiddenLabel()
                        ->disk(Branding::DISK)
                        ->directory(Branding::DIRECTORY)
                        ->visibility('private')
                        ->acceptedFileTypes(array_values(array_unique(Branding::TYPES)))
                        ->maxSize(Branding::MAX_KILOBYTES)
                        ->rules([new LogoImage])
                        ->imagePreviewHeight('120')
                        ->panelAspectRatio('4:1')
                        ->panelLayout('integrated')
                        ->removeUploadedFileButtonPosition('right')
                        ->uploadingMessage('Uploading logo…')
                        // Preview through the same route the sign-in screen uses.
                        ->getUploadedFileUsing(function (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array {
                            $info = $component->getUploadedFile($file, $storedFileNames);

                            return $info ? ['url' => Branding::urlFor($file), 'type' => Branding::contentType($file)] + $info : null;
                        })
                        ->helperText('PNG, JPG, WebP or SVG, up to 1 MB. A wide logo on a transparent background works best; it is shown about 40 px high. Use the × to remove it.'),
                ]),
            Section::make('IT helpdesk contact')
                ->description('Shown on the sign-in screen.')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('support_phone')->tel()->maxLength(40),
                    TextInput::make('support_email')->email()->maxLength(150),
                ]),
            Section::make('Login screen greetings')
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Toggle::make('login_greetings_enabled')->label('Show occasion greetings and the quote of the day'),
                    TextInput::make('hijri_offset_days')->label('Hijri date adjustment (days)')->numeric()->minValue(-2)->maxValue(2)->required()
                        ->helperText('Use -1 if Islamic dates in Pakistan are usually a day behind the calculated (Saudi) date.'),
                ]),
        ]);
    }

    protected function beforeSave(): void
    {
        $this->previousLogo = app(GeneralSettings::class)->logo_path;
    }

    protected function afterSave(): void
    {
        if ($this->previousLogo !== null && $this->previousLogo !== app(GeneralSettings::class)->logo_path) {
            Branding::delete($this->previousLogo);
        }
    }
}
