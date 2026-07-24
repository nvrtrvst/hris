import { Combobox, ComboboxInput, ComboboxButton, ComboboxOption, ComboboxOptions, Label, Field } from '@headlessui/react'
import { ChevronDown, Check } from 'lucide-react'

export default function ComboSelect({ label, value, onChange, options, placeholder, error, required }) {
    const optionList = typeof options === 'function' ? [] : (options || []);
    const selected = optionList.find(o => o.value === value) || null;

    return (
        <Field>
            {label && (
                <Label className="block text-sm font-bold text-primary/80 mb-1.5">
                    {label} {required && <span className="text-rose-500">*</span>}
                </Label>
            )}
            <Combobox
                value={selected}
                onChange={(opt) => onChange(opt ? opt.value : '')}
            >
                <div className="relative">
                    <ComboboxInput
                        placeholder={placeholder || 'Pilih…'}
                        className="w-full rounded-xl border-border bg-surface px-4 py-2.5 pr-10 text-sm text-primary ring-1 ring-black/5 placeholder:text-text-secondary/50 focus:outline-none focus:ring-2 focus:ring-primary"
                        displayValue={(opt) => opt?.label || ''}
                    />
                    <ComboboxButton className="group absolute inset-y-0 right-0 px-2.5">
                        <ChevronDown className="h-4 w-4 text-text-secondary group-data-open:rotate-180 transition-transform" />
                    </ComboboxButton>
                </div>
                <ComboboxOptions
                    anchor="bottom"
                    className="z-50 mt-1 w-(--input-width) rounded-xl border border-border bg-white p-1 shadow-lg empty:invisible"
                >
                    {optionList.map((opt) => (
                        <ComboboxOption
                            key={opt.value}
                            value={opt}
                            className="flex cursor-pointer items-center justify-between rounded-lg px-3 py-2 text-sm text-primary data-focus:bg-primary/10"
                        >
                            {opt.label}
                            {selected?.value === opt.value && (
                                <Check className="h-4 w-4 text-primary" />
                            )}
                        </ComboboxOption>
                    ))}
                </ComboboxOptions>
            </Combobox>
            {error && <p className="mt-1 text-xs font-semibold text-rose-600">{error}</p>}
        </Field>
    );
}
