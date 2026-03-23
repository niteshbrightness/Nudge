import { ChevronDown, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

interface ProjectOption {
    id: number;
    name: string;
}

interface ProjectMultiSelectProps {
    options: ProjectOption[];
    defaultSelected?: number[];
    name?: string;
    placeholder?: string;
}

export function ProjectMultiSelect({
    options,
    defaultSelected = [],
    name = 'project_ids',
    placeholder = 'Select projects…',
}: ProjectMultiSelectProps) {
    const [selectedIds, setSelectedIds] = useState<number[]>(defaultSelected);
    const [search, setSearch] = useState('');
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);

    const filtered = options.filter((o) => o.name.toLowerCase().includes(search.toLowerCase()));

    const toggle = (id: number) => {
        setSelectedIds((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
    };

    const remove = (id: number, e: React.MouseEvent) => {
        e.stopPropagation();
        setSelectedIds((prev) => prev.filter((x) => x !== id));
    };

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

    const selectedOptions = options.filter((o) => selectedIds.includes(o.id));

    return (
        <div ref={containerRef} className="relative">
            {/* Hidden inputs for form submission */}
            {selectedIds.length > 0 ? (
                selectedIds.map((id) => <input key={id} type="hidden" name={`${name}[]`} value={id} />)
            ) : (
                <input type="hidden" name={name} value="" />
            )}

            {/* Trigger */}
            <div
                role="button"
                tabIndex={0}
                onClick={() => setIsOpen((o) => !o)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        setIsOpen((o) => !o);
                    }
                }}
                className={cn(
                    'flex min-h-9 w-full cursor-pointer flex-wrap items-center gap-1.5 rounded-md border border-input bg-transparent px-3 py-1.5 text-sm shadow-xs transition-colors',
                    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:outline-none focus-visible:ring-[3px]',
                    isOpen && 'border-ring ring-ring/50 ring-[3px]',
                )}
            >
                {selectedOptions.length > 0 ? (
                    <>
                        {selectedOptions.map((o) => (
                            <Badge key={o.id} variant="secondary" className="gap-1 pr-1">
                                {o.name}
                                <button
                                    type="button"
                                    onClick={(e) => remove(o.id, e)}
                                    className="ml-0.5 rounded-full opacity-60 hover:opacity-100"
                                    aria-label={`Remove ${o.name}`}
                                >
                                    <X className="size-3" />
                                </button>
                            </Badge>
                        ))}
                    </>
                ) : (
                    <span className="text-muted-foreground">{placeholder}</span>
                )}
                <ChevronDown
                    className={cn('ml-auto size-4 shrink-0 opacity-50 transition-transform', isOpen && 'rotate-180')}
                />
            </div>

            {/* Dropdown */}
            {isOpen && (
                <div className="absolute z-50 mt-1 w-full rounded-md border border-input bg-popover shadow-md">
                    <div className="p-2">
                        <Input
                            ref={searchRef}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search projects…"
                            className="h-8"
                        />
                    </div>
                    <ul className="max-h-48 overflow-y-auto pb-1">
                        {filtered.length === 0 ? (
                            <li className="px-3 py-4 text-center text-sm text-muted-foreground">No projects found.</li>
                        ) : (
                            filtered.map((o) => (
                                <li
                                    key={o.id}
                                    role="option"
                                    aria-selected={selectedIds.includes(o.id)}
                                    onClick={() => toggle(o.id)}
                                    className="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-accent hover:text-accent-foreground"
                                >
                                    <span
                                        className={cn(
                                            'flex size-4 shrink-0 items-center justify-center rounded-sm border border-primary',
                                            selectedIds.includes(o.id)
                                                ? 'bg-primary text-primary-foreground'
                                                : 'opacity-50',
                                        )}
                                    >
                                        {selectedIds.includes(o.id) && (
                                            <svg viewBox="0 0 12 12" className="size-3 fill-current" aria-hidden>
                                                <path d="M10 3L5 8.5 2 5.5" stroke="currentColor" strokeWidth="1.5" fill="none" strokeLinecap="round" strokeLinejoin="round" />
                                            </svg>
                                        )}
                                    </span>
                                    {o.name}
                                </li>
                            ))
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}
