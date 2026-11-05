import { Transition } from '@headlessui/react';
import { Link } from '@inertiajs/react';
import { createContext, useContext, useEffect, useRef, useState } from 'react';

const DropDownContext = createContext();

const Dropdown = ({ children, closeOnOutsideClick = false }) => {
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);

    const toggleOpen = () => {
        setOpen((previousState) => !previousState);
    };

    useEffect(() => {
        if (!open || !closeOnOutsideClick) return undefined;

        const onPointerDown = (event) => {
            if (rootRef.current && !rootRef.current.contains(event.target)) {
                setOpen(false);
            }
        };
        const onKeyDown = (event) => {
            if (event.key === 'Escape') setOpen(false);
        };

        document.addEventListener('pointerdown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('pointerdown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open, closeOnOutsideClick]);

    return (
        <DropDownContext.Provider value={{ open, setOpen, toggleOpen, closeOnOutsideClick }}>
            <div ref={rootRef} className="relative">{children}</div>
        </DropDownContext.Provider>
    );
};

const Trigger = ({ children }) => {
    const { open, setOpen, toggleOpen, closeOnOutsideClick } = useContext(DropDownContext);

    return (
        <>
            <div onClick={toggleOpen}>{children}</div>

            {open && !closeOnOutsideClick && (
                <div
                    className="fixed inset-0 z-40"
                    onClick={() => setOpen(false)}
                ></div>
            )}
        </>
    );
};

const Content = ({
    align = 'right',
    width = '48',
    contentClasses = 'py-2 bg-white',
    children,
}) => {
    const { open, setOpen } = useContext(DropDownContext);

    let alignmentClasses = 'origin-top';
    let positionClasses = 'absolute z-50 mt-2';

    if (align === 'top') {
        alignmentClasses = 'origin-bottom start-0';
        positionClasses = 'absolute z-50 bottom-full mb-3';
    } else if (align === 'left') {
        alignmentClasses = 'ltr:origin-top-left rtl:origin-top-right start-0';
    } else if (align === 'right') {
        alignmentClasses = 'ltr:origin-top-right rtl:origin-top-left end-0';
    }

    let widthClasses = '';

    if (width === '48') {
        widthClasses = 'w-48';
    }

    return (
        <>
            <Transition
                show={open}
                enter="transition ease-out duration-200"
                enterFrom="opacity-0 scale-95 translate-y-2"
                enterTo="opacity-100 scale-100 translate-y-0"
                leave="transition ease-in duration-100"
                leaveFrom="opacity-100 scale-100 translate-y-0"
                leaveTo="opacity-0 scale-95 translate-y-2"
            >
                    <div
                        className={`${positionClasses} rounded-xl shadow-elevated ${alignmentClasses} ${widthClasses} ring-1 ring-black/5 overflow-hidden border border-border`}
                        onClick={() => setOpen(false)}
                    >
                        <div className={contentClasses}>
                        {children}
                    </div>
                </div>
            </Transition>
        </>
    );
};

const DropdownLink = ({ className = '', children, ...props }) => {
    return (
        <Link
            {...props}
            className={
                'block w-full px-4 py-3 text-start text-sm font-medium leading-5 text-text-primary transition duration-150 ease-in-out hover:bg-primary-50 hover:text-primary focus:bg-primary-50 focus:outline-none ' +
                className
            }
        >
            {children}
        </Link>
    );
};

Dropdown.Trigger = Trigger;
Dropdown.Content = Content;
Dropdown.Link = DropdownLink;

export default Dropdown;
