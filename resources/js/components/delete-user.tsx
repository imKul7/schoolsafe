import { useForm } from '@inertiajs/react';
import { AlertTriangle, LoaderCircle, LockKeyhole, Trash2 } from 'lucide-react';
import type { FormEventHandler } from 'react';
import { useRef } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function DeleteUser() {
    const passwordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    function closeModal(): void {
        clearErrors();
        reset();
    }

    const deleteUser: FormEventHandler = (event) => {
        event.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: closeModal,
            onError: () => passwordInput.current?.focus(),
            onFinish: () => reset(),
        });
    };

    return (
        <section className="profile-danger-card rounded-[26px] p-5 sm:p-6">
            <div className="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div className="flex items-start gap-4">
                    <span className="profile-danger-icon grid size-12 shrink-0 place-items-center rounded-2xl">
                        <AlertTriangle className="size-6" />
                    </span>

                    <div>
                        <p className="text-xs font-extrabold tracking-[0.14em] text-red-500 uppercase">Zona Berbahaya</p>

                        <h2 className="mt-2 text-lg font-extrabold tracking-[-0.02em] text-slate-950">Hapus akun secara permanen</h2>

                        <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                            Seluruh data dan sumber daya milik akun akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.
                        </p>
                    </div>
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button variant="destructive" className="profile-delete-trigger h-11 shrink-0 rounded-xl px-5 text-sm font-extrabold">
                            <Trash2 className="size-4" />
                            Hapus akun
                        </Button>
                    </DialogTrigger>

                    <DialogContent className="profile-delete-dialog sm:max-w-[540px]">
                        <DialogHeader>
                            <span className="profile-dialog-icon mb-2 grid size-12 place-items-center rounded-2xl">
                                <LockKeyhole className="size-6" />
                            </span>

                            <DialogTitle className="text-xl font-extrabold tracking-[-0.02em] text-slate-950">
                                Konfirmasi penghapusan akun
                            </DialogTitle>

                            <DialogDescription className="pt-1 leading-6 text-slate-600">
                                Masukkan kata sandi saat ini untuk mengonfirmasi. Setelah akun dihapus, seluruh data tidak dapat dipulihkan.
                            </DialogDescription>
                        </DialogHeader>

                        <form className="space-y-5" onSubmit={deleteUser}>
                            <div className="grid gap-2">
                                <Label htmlFor="password" className="font-bold text-slate-700">
                                    Kata sandi saat ini
                                </Label>

                                <Input
                                    id="password"
                                    type="password"
                                    name="password"
                                    ref={passwordInput}
                                    value={data.password}
                                    onChange={(event) => setData('password', event.target.value)}
                                    className="profile-dialog-input h-12 rounded-xl"
                                    placeholder="Masukkan kata sandi"
                                    autoComplete="current-password"
                                />

                                <InputError message={errors.password} />
                            </div>

                            <DialogFooter className="gap-2 sm:gap-3">
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        onClick={closeModal}
                                        className="profile-dialog-cancel h-11 rounded-xl px-5 font-bold"
                                    >
                                        Batal
                                    </Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    variant="destructive"
                                    disabled={processing}
                                    className="profile-dialog-delete h-11 rounded-xl px-5 font-bold"
                                >
                                    {processing ? <LoaderCircle className="size-4 animate-spin" /> : <Trash2 className="size-4" />}

                                    {processing ? 'Menghapus...' : 'Hapus akun permanen'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </section>
    );
}
