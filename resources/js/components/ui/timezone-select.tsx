import { ChevronDown } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

interface TimezoneOption {
    id: number;
    label: string;
}

interface TimezoneSelectProps {
    options: TimezoneOption[];
    defaultValue?: number;
    value?: number | null;
    name?: string;
    placeholder?: string;
    onChange?: (value: number | null) => void;
    onBlur?: () => void;
}

export function TimezoneSelect({
    options,
    defaultValue,
    value,
    name = 'timezone_id',
    placeholder = 'Select timezone',
    onChange,
    onBlur,
}: TimezoneSelectProps) {
    const isControlled = value !== undefined;
    const [selectedId, setSelectedId] = useState<number | undefined>(defaultValue);
    const [search, setSearch] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);

    const resolvedId = isControlled ? (value ?? undefined) : selectedId;

    const filtered = options.filter((o) => o.label.toLowerCase().includes(search.toLowerCase()));

    const selectedOption = options.find((o) => o.id === resolvedId);

    useEffect(() => {
        if (isOpen) {
            setTimeout(() => searchRef.current?.focus(), 0);
        } else {
            setSearch('');
        }
    }, [isOpen]);

    useEffect(() => {
        const handleMouseDown = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setIsOpen(false);
            }
        };

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                setIsOpen(false);
            }
        };

        if (isOpen) {
            document.addEventListener('mousedown', handleMouseDown);
            document.addEventListener('keydown', handleKeyDown);
        }

        return () => {
            document.removeEventListener('mousedown', handleMouseDown);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [isOpen]);

    const select = (id: number) => {
        if (!isControlled) {
            setSelectedId(id);
        }
        onChange?.(id);
        setIsOpen(false);
        onBlur?.();
    };

    return (
        <div ref={containerRef} className="relative">
            <input type="hidden" name={name} value={resolvedId ?? ''} />

            <div
                role="combobox"
                aria-expanded={isOpen}
                tabIndex={0}
                onClick={() => setIsOpen((o) => !o)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        setIsOpen((o) => !o);
                    }
                }}
                className={cn(
                    'flex h-9 w-full cursor-pointer items-center rounded-md border border-input bg-transparent px-3 py-1.5 text-sm shadow-xs transition-colors',
                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-none focus-visible:ring-[3px]',
                    isOpen && 'border-ring ring-ring/50 ring-[3px]',
                )}
            >
                <span className={cn('flex-1 truncate', !selectedOption && 'text-muted-foreground')}>
                    {selectedOption ? selectedOption.label : placeholder}
                </span>
                <ChevronDown className={cn('ml-2 size-4 shrink-0 opacity-50 transition-transform', isOpen && 'rotate-180')} />
            </div>

            {isOpen && (
                <div className="absolute z-50 mt-1 w-full rounded-md border border-input bg-popover shadow-md">
                    <div className="p-2">
                        <Input
                            ref={searchRef}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search timezones…"
                            className="h-8"
                        />
                    </div>
                    <ul className="max-h-48 overflow-y-auto pb-1" role="listbox">
                        {filtered.length === 0 ? (
                            <li className="px-3 py-4 text-center text-sm text-muted-foreground">No timezones found.</li>
                        ) : (
                            filtered.map((o) => (
                                <li
                                    key={o.id}
                                    role="option"
                                    aria-selected={o.id === resolvedId}
                                    onClick={() => select(o.id)}
                                    className={cn(
                                        'cursor-pointer px-3 py-2 text-sm hover:bg-accent hover:text-accent-foreground',
                                        o.id === resolvedId && 'bg-accent text-accent-foreground font-medium',
                                    )}
                                >
                                    {o.label}
                                </li>
                            ))
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}
