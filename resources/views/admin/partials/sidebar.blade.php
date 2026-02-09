{{-- Admin Sidebar --}}
<aside class="admin-sidebar" 
       x-show="sidebarOpen"
       x-transition:enter="transition ease-out duration-300"
       x-transition:enter-start="transform translate-x-full"
       x-transition:enter-end="transform translate-x-0"
       x-transition:leave="transition ease-in duration-300"
       x-transition:leave-start="transform translate-x-0"
       x-transition:leave-end="transform translate-x-full"
       @click.away="if (!isDesktop) sidebarOpen = false">
    
    <!-- Sidebar Header -->
    <div class="p-6 border-b border-gray-700">
        <div class="flex items-center justify-between">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-3 space-x-reverse">
                <div class="w-10 h-10 bg-gradient-to-br from-green-400 to-blue-500 rounded-lg flex items-center justify-center">
                    <i class="fas fa-shield-alt text-white text-xl"></i>
                </div>
                <div>
                    <h2 class="text-white font-bold text-lg">پنل مدیریت</h2>
                    <p class="text-gray-400 text-xs">EarthCoop</p>
                </div>
            </a>
            <!-- Close Button (Mobile Only) -->
            <button @click="sidebarOpen = false" 
                    x-show="!isDesktop"
                    class="md:hidden text-gray-400 hover:text-white transition-colors p-2"
                    style="display: none;">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
    </div>
    
    <!-- Sidebar Navigation -->
    <nav class="p-4">
        <ul class="space-y-2">
            <!-- Dashboard -->
            <li>
                <a href="{{ route('admin.dashboard') }}" 
                   class="flex items-center space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->routeIs('admin.dashboard') && !request()->has('general') && !request()->has('activate') && !request()->has('content') ? 'bg-gray-700' : '' }}">
                    <i class="fas fa-home w-5"></i>
                    <span>داشبورد</span>
                </a>
            </li>
            
            <!-- Najm Hoda -->
            @hasPermission('najm-hoda.view-dashboard')
            <li>
                <a href="{{ route('admin.najm-hoda.index') }}" 
                   class="flex items-center space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->routeIs('admin.najm-hoda.*') ? 'bg-gray-700' : '' }}">
                    <i class="fas fa-robot w-5"></i>
                    <span>نجم‌هدا</span>
                </a>
            </li>
            @endhasPermission

            <!-- Najm Bahar -->
            <li>
                <a href="{{ route('admin.najm-bahar.dashboard') }}"
                   class="flex items-center space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->routeIs('admin.najm-bahar.*') ? 'bg-gray-700' : '' }}">
                    <i class="fas fa-star w-5"></i>
                    <span>نجم بهار</span>
                </a>
            </li>
            
            <!-- Users -->
            @hasPermission('users.view')
            <li>
                <a href="{{ route('admin.users.index') }}" 
                   class="flex items-center space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->routeIs('admin.users.*') ? 'bg-gray-700' : '' }}">
                    <i class="fas fa-users w-5"></i>
                    <span>مدیریت کاربران</span>
                </a>
            </li>
            @endhasPermission
            
            <!-- Roles & Permissions -->
            @hasPermission('roles.manage')
            <li>
                <a href="{{ route('admin.roles.index') }}" 
                   class="flex items-center space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->routeIs('admin.roles.*') || request()->routeIs('admin.permissions.*') ? 'bg-gray-700' : '' }}">
                    <i class="fas fa-user-shield w-5"></i>
                    <span>نقش‌ها و دسترسی‌ها</span>
                </a>
            </li>
            @endhasPermission
            
            <!-- Groups -->
            @hasPermission('groups.view')
            <li>
                <a href="{{ route('admin.groups.index') }}" 
                   class="flex items-center space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->routeIs('admin.groups.*') || request()->routeIs('admin.group.*') ? 'bg-gray-700' : '' }}">
                    <i class="fas fa-users-cog w-5"></i>
                    <span>مدیریت گروه‌ها</span>
                </a>
            </li>
            @endhasPermission
            
            <!-- System Settings -->
            <li>
                <a href="{{ route('admin.system-settings.index') }}" 
                   class="flex items-center space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->routeIs('admin.system-settings.*') || request()->routeIs('admin.invitation_codes.*') || request()->routeIs('admin.category.*') || request()->routeIs('admin.activate.*') || request()->routeIs('admin.group.setting.*') ? 'bg-gray-700' : '' }}">
                    <i class="fas fa-cog w-5"></i>
                    <span>تنظیمات سیستمی</span>
                </a>
            </li>
            <!-- Reputation Settings -->
            @hasPermission('system-settings.manage')
            <li>
                <a href="{{ route('admin.reputation.index') }}"
                   class="flex items-center space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->routeIs('admin.reputation.*') || request()->is('admin/system-settings/reputation') ? 'bg-gray-700' : '' }}">
                    <i class="fas fa-star w-5"></i>
                    <span>امتیازدهی (Reputation)</span>
                </a>
            </li>
            @endhasPermission
            
            <!-- Activation Management -->
            <li>
                <a href="{{ route('admin.dashboard', ['activate']) }}" 
                   class="flex items-center space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->has('activate') ? 'bg-gray-700' : '' }}">
                    <i class="fas fa-check-circle w-5"></i>
                    <span>مدیریت فعال‌سازی‌ها</span>
                </a>
            </li>
            
            <!-- Content Management -->
            @hasPermission('blog.view-dashboard')
            <li>
                <a href="{{ route('admin.content.index') }}" 
                   class="flex items-center space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->routeIs('admin.content.*') || request()->routeIs('admin.announcement.*') || request()->routeIs('admin.pages.*') || request()->routeIs('admin.rule.*') || request()->routeIs('admin.welcome-page') || request()->routeIs('admin.najm-page') ? 'bg-gray-700' : '' }}">
                    <i class="fas fa-file-alt w-5"></i>
                    <span>مدیریت محتوا</span>
                </a>
            </li>
            @endhasPermission
            
            <!-- Reports -->
            @hasPermission('reports.view')
            <li>
                <a href="{{ route('admin.reports.index') }}" 
                   class="flex items-center space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->routeIs('admin.reports.*') ? 'bg-gray-700' : '' }}">
                    <i class="fas fa-flag w-5"></i>
                    <span>گزارشات</span>
                </a>
            </li>
            @endhasPermission
            
            <!-- FAQ Questions -->
            <li>
                <a href="{{ route('admin.faq.index') }}" 
                   class="flex items-center space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->routeIs('admin.faq.*') ? 'bg-gray-700' : '' }}">
                    <i class="fas fa-question-circle w-5"></i>
                    <span>سوالات متداول</span>
                </a>
            </li>

            <!-- Knowledge Base -->
            @hasPermission('tickets.manage')
            <li x-data="{ open: {{ request()->routeIs('admin.kb.*') ? 'true' : 'false' }} }">
                <button @click="open = !open" 
                        class="w-full flex items-center justify-between space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->routeIs('admin.kb.*') ? 'bg-gray-700' : '' }}">
                    <div class="flex items-center space-x-3 space-x-reverse">
                        <i class="fas fa-book-open w-5"></i>
                        <span>پایگاه دانش</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                </button>
                <ul x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 transform -translate-y-2"
                    x-transition:enter-end="opacity-100 transform translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 transform translate-y-0"
                    x-transition:leave-end="opacity-0 transform -translate-y-2"
                    class="mr-8 mt-2 space-y-1">
                    <li>
                        <a href="{{ route('admin.kb.articles.index') }}"
                           class="flex items-center space-x-3 space-x-reverse px-4 py-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors {{ request()->routeIs('admin.kb.articles.*') ? 'bg-gray-700 text-white' : '' }}">
                            <i class="fas fa-file-alt w-4"></i>
                            <span>مقالات</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.kb.categories.index') }}"
                           class="flex items-center space-x-3 space-x-reverse px-4 py-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors {{ request()->routeIs('admin.kb.categories.*') ? 'bg-gray-700 text-white' : '' }}">
                            <i class="fas fa-layer-group w-4"></i>
                            <span>دسته‌بندی</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.kb.tags.index') }}"
                           class="flex items-center space-x-3 space-x-reverse px-4 py-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors {{ request()->routeIs('admin.kb.tags.*') ? 'bg-gray-700 text-white' : '' }}">
                            <i class="fas fa-tags w-4"></i>
                            <span>تگ‌ها</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endhasPermission

            <!-- Support Management -->
            @hasPermission('tickets.manage')
            <li x-data="{ open: {{ (request()->routeIs('admin.tickets.*') || request()->routeIs('admin.support-chat.*')) ? 'true' : 'false' }} }">
                <button @click="open = !open" 
                        class="w-full flex items-center justify-between space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ (request()->routeIs('admin.tickets.*') || request()->routeIs('admin.support-chat.*')) ? 'bg-gray-700' : '' }}">
                    <div class="flex items-center space-x-3 space-x-reverse">
                        <i class="fas fa-headset w-5"></i>
                        <span>پشتیبانی</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                </button>
                <ul x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 transform -translate-y-2"
                    x-transition:enter-end="opacity-100 transform translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 transform translate-y-0"
                    x-transition:leave-end="opacity-0 transform -translate-y-2"
                    class="mr-8 mt-2 space-y-1">
                    <li>
                        <a href="{{ route('admin.support-chat.index') }}"
                           class="flex items-center space-x-3 space-x-reverse px-4 py-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors {{ request()->routeIs('admin.support-chat.*') ? 'bg-gray-700 text-white' : '' }}">
                            <i class="fas fa-comments w-4"></i>
                            <span>چت پشتیبانی</span>
                            @php
                                $waitingChats = \App\Models\SupportChat::where('status', 'waiting')->count();
                            @endphp
                            @if($waitingChats > 0)
                                <span class="mr-auto text-xs bg-red-500 text-white px-2 py-0.5 rounded-full">{{ $waitingChats }}</span>
                            @endif
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.tickets.index') }}"
                           class="flex items-center space-x-3 space-x-reverse px-4 py-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors {{ request()->routeIs('admin.tickets.*') ? 'bg-gray-700 text-white' : '' }}">
                            <i class="fas fa-ticket-alt w-4"></i>
                            <span>تیکت‌ها</span>
                        </a>
                    </li>
                </ul>
            </li>
            @endhasPermission
            
            <!-- Email Management -->
            <li x-data="{ open: {{ (request()->routeIs('admin.emails.*') || request()->routeIs('admin.system-emails.*')) ? 'true' : 'false' }} }">
                <button @click="open = !open" 
                        class="w-full flex items-center justify-between space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->routeIs('admin.emails.*') || request()->routeIs('admin.system-emails.*') ? 'bg-gray-700' : '' }}">
                    <div class="flex items-center space-x-3 space-x-reverse">
                        <i class="fas fa-envelope w-5"></i>
                        <span>مدیریت ایمیل</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                </button>
                <ul x-show="open" 
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 transform -translate-y-2"
                    x-transition:enter-end="opacity-100 transform translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 transform translate-y-0"
                    x-transition:leave-end="opacity-0 transform -translate-y-2"
                    class="mr-8 mt-2 space-y-1">
                    <li>
                        <a href="{{ route('admin.emails.index') }}" 
                           class="flex items-center space-x-3 space-x-reverse px-4 py-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors {{ request()->routeIs('admin.emails.index') || request()->routeIs('admin.emails.create') || request()->routeIs('admin.emails.edit') ? 'bg-gray-700 text-white' : '' }}">
                            <i class="fas fa-file-alt w-4"></i>
                            <span>قالب‌های ایمیل</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.system-emails.index') }}" 
                           class="flex items-center space-x-3 space-x-reverse px-4 py-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors {{ request()->routeIs('admin.system-emails.*') ? 'bg-gray-700 text-white' : '' }}">
                            <i class="fas fa-at w-4"></i>
                            <span>ایمیل‌های سیستم</span>
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('admin.emails.send') }}" 
                           class="flex items-center space-x-3 space-x-reverse px-4 py-2 rounded-lg text-gray-300 hover:bg-gray-700 hover:text-white transition-colors {{ request()->routeIs('admin.emails.send') ? 'bg-gray-700 text-white' : '' }}">
                            <i class="fas fa-paper-plane w-4"></i>
                            <span>ارسال ایمیل</span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <!-- Stock & Auction -->
            @hasPermission('stock.view-dashboard')
            <li>
                <a href="{{ route('admin.stock.index') }}" 
                   class="flex items-center space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->routeIs('admin.stock.*') || request()->routeIs('admin.auction.*') || request()->routeIs('admin.wallet.*') || request()->routeIs('admin.holdings.*') ? 'bg-gray-700' : '' }}">
                    <i class="fas fa-chart-line w-5"></i>
                    <span>سهام و حراج</span>
                </a>
            </li>
            @endhasPermission
            
            <!-- Blog -->
            @hasPermission('blog.view-dashboard')
            <li>
                <a href="{{ route('admin.blog.dashboard') }}" 
                   class="flex items-center space-x-3 space-x-reverse px-4 py-3 rounded-lg text-white hover:bg-gray-700 transition-colors {{ request()->routeIs('admin.blog.*') ? 'bg-gray-700' : '' }}">
                    <i class="fas fa-blog w-5"></i>
                    <span>مدیریت وبلاگ</span>
                </a>
            </li>
            @endhasPermission
            
            <!-- Divider -->
            <li class="pt-4">
                <hr class="border-gray-700">
            </li>
            
            <!-- Back to Home -->
            <li>
                <a href="{{ route('home') }}" 
                   class="flex items-center space-x-3 space-x-reverse px-4 py-3 rounded-lg text-gray-400 hover:bg-gray-700 hover:text-white transition-colors">
                    <i class="fas fa-arrow-left w-5"></i>
                    <span>بازگشت به سایت</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

