<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:users,id',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|string|max:255',
            'role' => 'nullable|string|in:admin,user,owner',
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
            ],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'الاسم مطلوب.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.unique' => 'هذا البريد الإلكتروني مستخدم بالفعل.',
            'phone.max' => 'رقم الهاتف يجب ان يكون اقل من 255 حرف.',
            'password.required' => 'كلمة المرور مطلوبة.',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين.',
            'password.min' => 'كلمة المرور يجب ان تكون 8 احرف على الاقل.',
            'password.letters' => 'كلمة المرور يجب ان تحتوي على حروف.',
            'password.mixedCase' => 'كلمة المرور يجب ان تحتوي على حروف كبيرة وصغيرة.',
            'password.numbers' => 'كلمة المرور يجب ان تحتوي على ارقام.',
        ];
    }
}