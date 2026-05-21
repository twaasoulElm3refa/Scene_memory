<?php

namespace App\Providers;

use App\Repositories\Contracts\Auth\AdminAuthRepositoryInterface;
use App\Repositories\Contracts\Auth\AuthRepositoryInterface;
use App\Repositories\Contracts\Benefits\BenefitRepositoryInterface;
use App\Repositories\Contracts\Carts\CartRepositoryInterface;
use App\Repositories\Contracts\Categories\CategoryRepositoryInterface;
use App\Repositories\Contracts\Cities\CityRepositoryInterface;
use App\Repositories\Contracts\Comments\CommentRepositoryInterface;
use App\Repositories\Contracts\Contacts\ContactRepositoryInterface;
use App\Repositories\Contracts\Countries\CountryRepositoryInterface;
use App\Repositories\Contracts\EventImages\EventImageRepositoryInterface;
use App\Repositories\Contracts\Events\EventRepositoryInterface;
use App\Repositories\Contracts\Footers\FooterRepositoryInterface;
use App\Repositories\Contracts\Likes\LikeRepositoryInterface;
use App\Repositories\Contracts\Newsletters\NewsletterRepositoryInterface;
use App\Repositories\Contracts\Notifications\NotificationRepositoryInterface;
use App\Repositories\Contracts\Plans\PlanRepositoryInterface;
use App\Repositories\Contracts\Purchases\PurchaseRepositoryInterface;
use App\Repositories\Contracts\Reports\ReportRepositoryInterface;
use App\Repositories\Contracts\Requests\RequestRepositoryInterface;
use App\Repositories\Contracts\SubCategories\SubCategoryRepositoryInterface;
use App\Repositories\Contracts\Subscriptions\SubscriptionRepositoryInterface;
use App\Repositories\Contracts\Users\UserRepositoryInterface;
use App\Repositories\Contracts\Withdrawals\WithdrawalRepositoryInterface;
use App\Repositories\Contracts\Wallets\WalletRepositoryInterface;
use App\Repositories\Contracts\Wishlists\WishlistRepositoryInterface;
use App\Repositories\Eloquent\Auth\AdminAuthRepository;
use App\Repositories\Eloquent\Auth\AuthRepository;
use App\Repositories\Eloquent\Benefits\BenefitRepository;
use App\Repositories\Eloquent\Carts\CartRepository;
use App\Repositories\Eloquent\Categories\CategoryRepository;
use App\Repositories\Eloquent\Cities\CityRepository;
use App\Repositories\Eloquent\Comments\CommentRepository;
use App\Repositories\Eloquent\Contacts\ContactRepository;
use App\Repositories\Eloquent\Countries\CountryRepository;
use App\Repositories\Eloquent\EventImages\EventImageRepository;
use App\Repositories\Eloquent\Events\EventRepository;
use App\Repositories\Eloquent\Footers\FooterRepository;
use App\Repositories\Eloquent\Likes\LikeRepository;
use App\Repositories\Eloquent\Newsletters\NewsletterRepository;
use App\Repositories\Eloquent\Notifications\NotificationRepository;
use App\Repositories\Eloquent\Plans\PlanRepository;
use App\Repositories\Eloquent\Purchases\PurchaseRepository;
use App\Repositories\Eloquent\Reports\ReportRepository;
use App\Repositories\Eloquent\Requests\RequestRepository;
use App\Repositories\Eloquent\SubCategories\SubCategoryRepository;
use App\Repositories\Eloquent\Subscriptions\SubscriptionRepository;
use App\Repositories\Eloquent\Users\UserRepository;
use App\Repositories\Eloquent\Withdrawals\WithdrawalRepository;
use App\Repositories\Eloquent\Wallets\WalletRepository;
use App\Repositories\Eloquent\Wishlists\WishlistRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Auth
        $this->app->bind(AuthRepositoryInterface::class, AuthRepository::class);

        // Users
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(NotificationRepositoryInterface::class, NotificationRepository::class);

        // Categories / SubCategories
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(SubCategoryRepositoryInterface::class, SubCategoryRepository::class);

        // Countries / Cities
        $this->app->bind(CountryRepositoryInterface::class, CountryRepository::class);
        $this->app->bind(CityRepositoryInterface::class, CityRepository::class);

        // Events
        $this->app->bind(EventRepositoryInterface::class, EventRepository::class);
        $this->app->bind(EventImageRepositoryInterface::class, EventImageRepository::class);

        // Interactions
        $this->app->bind(CommentRepositoryInterface::class, CommentRepository::class);
        $this->app->bind(LikeRepositoryInterface::class, LikeRepository::class);
        $this->app->bind(WishlistRepositoryInterface::class, WishlistRepository::class);

        // Commerce
        $this->app->bind(CartRepositoryInterface::class, CartRepository::class);
        $this->app->bind(PurchaseRepositoryInterface::class, PurchaseRepository::class);
        $this->app->bind(WalletRepositoryInterface::class, WalletRepository::class);
        $this->app->bind(WithdrawalRepositoryInterface::class, WithdrawalRepository::class);

        // Plans
        $this->app->bind(PlanRepositoryInterface::class, PlanRepository::class);
        $this->app->bind(BenefitRepositoryInterface::class, BenefitRepository::class);
        $this->app->bind(SubscriptionRepositoryInterface::class, SubscriptionRepository::class);

        // Admin content
        $this->app->bind(ContactRepositoryInterface::class, ContactRepository::class);
        $this->app->bind(NewsletterRepositoryInterface::class, NewsletterRepository::class);
        $this->app->bind(FooterRepositoryInterface::class, FooterRepository::class);
        $this->app->bind(ReportRepositoryInterface::class, ReportRepository::class);
        $this->app->bind(RequestRepositoryInterface::class, RequestRepository::class);

        $this->app->bind(AdminAuthRepositoryInterface::class, AdminAuthRepository::class);
    }

    public function boot(): void
    {
    }
}
