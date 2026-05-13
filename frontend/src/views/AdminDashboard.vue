<template>
  <div v-if="user?.role === 'admin'" class="min-h-screen bg-slate-50 flex">
    <aside class="w-64 bg-gradient-to-b from-[#1E3A8A] to-[#1D4ED8] text-white flex flex-col fixed h-full shadow-2xl">
      <div class="p-6 border-b border-white/10">
        <div class="flex items-center space-x-3">
          <div class="bg-white/15 p-2 rounded-xl">
            <Building2 class="w-6 h-6" />
          </div>
          <div>
            <h1 class="font-bold text-lg">PaBlotterMo Admin</h1>
            <p class="text-xs text-blue-200">Management Portal</p>
          </div>
        </div>
      </div>

      <nav class="flex-1 p-4 space-y-2">
        <button
          v-for="item in menuItems"
          :key="item.id"
          @click="activeTab = item.id"
          :class="[
            'w-full flex items-center space-x-3 px-4 py-3 rounded-lg transition',
            activeTab === item.id ? 'bg-[#15803D] text-white' : 'text-blue-100 hover:bg-white/10',
          ]"
        >
          <component :is="item.icon" class="w-5 h-5" />
          <span class="font-medium">{{ item.label }}</span>
        </button>
      </nav>

      <div class="p-4 border-t border-white/10">
        <div class="mb-3 px-4">
          <p class="text-sm font-medium">{{ user.name }}</p>
          <p class="text-xs text-blue-200">{{ user.email }}</p>
        </div>
        <button
          @click="handleLogout"
          class="w-full flex items-center space-x-3 px-4 py-3 rounded-lg text-blue-100 hover:bg-white/10 transition"
        >
          <LogOut class="w-5 h-5" />
          <span class="font-medium">Logout</span>
        </button>
      </div>
    </aside>

    <main class="flex-1 ml-64">
      <div class="p-8">
        <div class="mb-8">
          <h2 class="text-3xl font-bold text-slate-900 mb-2">{{ activeLabel }}</h2>
          <p class="text-slate-600">{{ activeDescription }}</p>
        </div>

        <template v-if="activeTab === 'dashboard'">
          <div v-if="loading" class="mb-6 text-gray-600">Loading dashboard data...</div>
          <div v-if="loadError" class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ loadError }}
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-2xl p-6 shadow-lg border border-slate-100">
              <div class="bg-purple-100 p-3 rounded-lg w-fit mb-3"><AlertCircle class="w-6 h-6 text-purple-600" /></div>
              <h3 class="text-sm font-medium text-gray-600 mb-1">Total Complaints</h3>
              <p class="text-3xl font-bold text-gray-900">{{ stats.total }}</p>
            </div>
            <div class="bg-white rounded-2xl p-6 shadow-lg border border-slate-100">
              <div class="bg-yellow-100 p-3 rounded-lg w-fit mb-3"><Clock class="w-6 h-6 text-yellow-600" /></div>
              <h3 class="text-sm font-medium text-gray-600 mb-1">Pending Cases</h3>
              <p class="text-3xl font-bold text-gray-900">{{ stats.pending }}</p>
            </div>
            <div class="bg-white rounded-2xl p-6 shadow-lg border border-slate-100">
              <div class="bg-blue-100 p-3 rounded-lg w-fit mb-3"><Search class="w-6 h-6 text-blue-600" /></div>
              <h3 class="text-sm font-medium text-gray-600 mb-1">Under Investigation</h3>
              <p class="text-3xl font-bold text-gray-900">{{ stats.investigating }}</p>
            </div>
            <div class="bg-white rounded-2xl p-6 shadow-lg border border-slate-100">
              <div class="bg-green-100 p-3 rounded-lg w-fit mb-3"><CheckCircle class="w-6 h-6 text-green-600" /></div>
              <h3 class="text-sm font-medium text-gray-600 mb-1">Resolved Cases</h3>
              <p class="text-3xl font-bold text-gray-900">{{ stats.resolved }}</p>
            </div>
            <div class="bg-white rounded-2xl p-6 shadow-lg border border-slate-100">
              <div class="bg-red-100 p-3 rounded-lg w-fit mb-3"><Ban class="w-6 h-6 text-red-600" /></div>
              <h3 class="text-sm font-medium text-gray-600 mb-1">Rejected</h3>
              <p class="text-3xl font-bold text-gray-900">{{ stats.rejected }}</p>
            </div>
          </div>

          <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Complaints</h3>
            <div class="space-y-4">
              <div
                v-for="complaint in recentComplaints"
                :key="complaint.id"
                class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition"
              >
                <div class="flex-1">
                  <p class="font-medium text-gray-900">{{ complaint.trackingNumber }}</p>
                  <p class="text-sm text-gray-600">{{ complaint.category }} - {{ complaint.residentName }}</p>
                </div>
                <StatusBadge :status="complaint.status" />
              </div>
            </div>
          </div>
        </template>

        <div v-else-if="activeTab === 'complaints'" class="bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden">
          <div class="px-6 py-4 border-b border-gray-200 space-y-4">
            <div class="flex items-center justify-between gap-4">
              <h3 class="text-lg font-semibold text-gray-900">All Complaints</h3>
              <div class="relative flex-1 max-w-md">
                <Search class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400" />
                <input
                  v-model="searchQuery"
                  type="text"
                  placeholder="Search by ID, name, or description..."
                  class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none"
                />
              </div>
              <button
                @click="showFilters = !showFilters"
                :class="[
                  'flex items-center space-x-2 px-4 py-2 rounded-lg border transition',
                  showFilters ? 'bg-[#1E3A8A] text-white border-[#1E3A8A]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50',
                ]"
              >
                <Filter class="w-4 h-4" />
                <span>Filters</span>
              </button>
            </div>

            <div v-if="showFilters" class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
              <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select v-model="statusFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                  <option value="All">All Status</option>
                  <option value="Pending">Pending</option>
                  <option value="Under Investigation">Under Investigation</option>
                  <option value="Resolved">Resolved</option>
                  <option value="Rejected">Rejected</option>
                </select>
              </div>
              <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                <select v-model="categoryFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                  <option value="All">All Categories</option>
                  <option value="Noise">Noise</option>
                  <option value="Theft">Theft</option>
                  <option value="Domestic">Domestic</option>
                  <option value="Property">Property</option>
                  <option value="Others">Others</option>
                </select>
              </div>
              <div v-if="hasActiveFilters" class="pt-7">
                <button @click="clearFilters" class="flex items-center space-x-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 rounded-lg transition border border-red-200">
                  <X class="w-4 h-4" />
                  <span>Clear All</span>
                </button>
              </div>
            </div>

            <div class="flex items-center justify-between text-sm text-gray-600">
              <span>Showing {{ filteredComplaints.length }} of {{ complaints.length }} complaints</span>
              <span v-if="hasActiveFilters" class="text-[#1E3A8A] font-medium">Filters applied</span>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full">
              <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase">ID</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase">Complainant</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase">Category</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase">Date</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase">Status</th>
                  <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr v-for="complaint in filteredComplaints" :key="complaint.id" class="hover:bg-gray-50 transition">
                  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ complaint.trackingNumber }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ complaint.residentName || "Anonymous" }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ complaint.category }}</td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ formatDate(complaint.dateSubmitted) }}</td>
                  <td class="px-6 py-4 whitespace-nowrap"><StatusBadge :status="complaint.status" /></td>
                  <td class="px-6 py-4 whitespace-nowrap text-sm">
                    <button @click="router.push(`/admin/complaint/${complaint.id}`)" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View Details">
                      <Eye class="w-4 h-4" />
                    </button>
                    <button
                      @click="deleteComplaint(complaint)"
                      :disabled="deletingComplaintId === complaint.id"
                      class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition disabled:opacity-50"
                      title="Delete Complaint"
                    >
                      <Trash2 class="w-4 h-4" />
                    </button>
                  </td>
                </tr>
                <tr v-if="filteredComplaints.length === 0">
                  <td colspan="6" class="px-6 py-12 text-center text-gray-500">No complaints found</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-else-if="activeTab === 'reports'" class="space-y-6">
          <div v-if="reportsLoading" class="text-gray-600">Loading report data...</div>
          <div v-if="reportsError" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ reportsError }}
          </div>

          <template v-if="reportData">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Complaints by Category</h3>
                <div class="space-y-3">
                  <div v-for="row in reportData.byCategory" :key="row.category">
                    <div class="flex justify-between text-sm mb-1">
                      <span class="text-gray-700">{{ row.category }}</span>
                      <span class="font-medium text-gray-900">{{ row.count }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                      <div class="bg-[#1E3A8A] h-2 rounded-full" :style="{ width: `${row.percentage}%` }" />
                    </div>
                  </div>
                </div>
              </div>

              <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Status Overview</h3>
                <div class="space-y-4">
                  <div class="flex items-center justify-between p-4 bg-yellow-50 rounded-lg">
                    <span class="text-gray-700">Pending</span>
                    <span class="text-2xl font-bold text-yellow-600">{{ reportData.statusOverview.pending }}</span>
                  </div>
                  <div class="flex items-center justify-between p-4 bg-blue-50 rounded-lg">
                    <span class="text-gray-700">Under Investigation</span>
                    <span class="text-2xl font-bold text-blue-600">{{ reportData.statusOverview.investigating }}</span>
                  </div>
                  <div class="flex items-center justify-between p-4 bg-green-50 rounded-lg">
                    <span class="text-gray-700">Resolved</span>
                    <span class="text-2xl font-bold text-green-600">{{ reportData.statusOverview.resolved }}</span>
                  </div>
                  <div class="flex items-center justify-between p-4 bg-red-50 rounded-lg">
                    <span class="text-gray-700">Rejected</span>
                    <span class="text-2xl font-bold text-red-600">{{ reportData.statusOverview.rejected }}</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
              <h3 class="text-lg font-semibold text-gray-900 mb-4">Export Reports</h3>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <button @click="exportReport('pdf')" class="px-6 py-3 bg-[#1E3A8A] text-white rounded-lg hover:bg-[#1e3a8ae6] transition">Export PDF</button>
                <button @click="exportReport('excel')" class="px-6 py-3 bg-[#15803D] text-white rounded-lg hover:bg-[#15803de6] transition">Export Excel</button>
                <button @click="exportReport('csv')" class="px-6 py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-800 transition">Export CSV</button>
              </div>
            </div>
          </template>
        </div>

        <div v-else-if="activeTab === 'settings'" class="space-y-6">
          <div v-if="settingsLoading" class="text-gray-600">Loading settings...</div>
          <div v-if="settingsError" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ settingsError }}
          </div>
          <div v-if="settingsSaveMessage" class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
            {{ settingsSaveMessage }}
          </div>

          <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">System Settings</h3>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Barangay Name</label>
                <input
                  v-model="settingsForm.barangayName"
                  type="text"
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contact Email</label>
                <input
                  v-model="settingsForm.contactEmail"
                  type="email"
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                <input
                  v-model="settingsForm.contactNumber"
                  type="text"
                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none"
                />
              </div>
            </div>
          </div>

          <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Notification Settings</h3>
            <div class="space-y-3">
              <label class="flex items-center space-x-3">
                <input v-model="settingsForm.notifyEmailNewComplaints" type="checkbox" class="w-5 h-5 text-[#1E3A8A] rounded" />
                <span class="text-gray-700">Email notifications for new complaints</span>
              </label>
              <label class="flex items-center space-x-3">
                <input v-model="settingsForm.notifySmsUrgentCases" type="checkbox" class="w-5 h-5 text-[#1E3A8A] rounded" />
                <span class="text-gray-700">SMS notifications for urgent cases</span>
              </label>
              <label class="flex items-center space-x-3">
                <input v-model="settingsForm.notifyDailySummaryReports" type="checkbox" class="w-5 h-5 text-[#1E3A8A] rounded" />
                <span class="text-gray-700">Daily summary reports</span>
              </label>
            </div>
          </div>

          <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">My Admin Account</h3>
            <div v-if="accountError" class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
              {{ accountError }}
            </div>
            <div v-if="accountMessage" class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
              {{ accountMessage }}
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                <input v-model="accountForm.name" type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                <input v-model="accountForm.email" type="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                <input v-model="accountForm.contactNumber" type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">New Password (Optional)</label>
                <div class="relative">
                  <input
                    v-model="accountForm.newPassword"
                    :type="showAccountNewPassword ? 'text' : 'password'"
                    class="w-full px-4 pr-11 py-3 border border-gray-300 rounded-lg"
                  />
                  <button
                    type="button"
                    @click="showAccountNewPassword = !showAccountNewPassword"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                    :aria-label="showAccountNewPassword ? 'Hide password' : 'Show password'"
                  >
                    <EyeOff v-if="showAccountNewPassword" class="w-4 h-4" />
                    <Eye v-else class="w-4 h-4" />
                  </button>
                </div>
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                <div class="relative">
                  <input
                    v-model="accountForm.newPasswordConfirmation"
                    :type="showAccountConfirmPassword ? 'text' : 'password'"
                    class="w-full px-4 pr-11 py-3 border border-gray-300 rounded-lg"
                  />
                  <button
                    type="button"
                    @click="showAccountConfirmPassword = !showAccountConfirmPassword"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                    :aria-label="showAccountConfirmPassword ? 'Hide password' : 'Show password'"
                  >
                    <EyeOff v-if="showAccountConfirmPassword" class="w-4 h-4" />
                    <Eye v-else class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
            <button
              @click="saveAccount"
              :disabled="accountSaving"
              class="mt-4 px-6 py-3 bg-[#1E3A8A] text-white rounded-lg hover:bg-[#1e3a8ae6] transition font-medium"
            >
              {{ accountSaving ? "Updating Account..." : "Update My Account" }}
            </button>
          </div>

          <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Add New Admin User</h3>
            <div v-if="adminCreateError" class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
              {{ adminCreateError }}
            </div>
            <div v-if="adminCreateMessage" class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
              {{ adminCreateMessage }}
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                <input v-model="newAdminForm.name" type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                <input v-model="newAdminForm.email" type="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Contact Number</label>
                <input v-model="newAdminForm.contactNumber" type="text" class="w-full px-4 py-3 border border-gray-300 rounded-lg" />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                <div class="relative">
                  <input
                    v-model="newAdminForm.password"
                    :type="showNewAdminPassword ? 'text' : 'password'"
                    class="w-full px-4 pr-11 py-3 border border-gray-300 rounded-lg"
                  />
                  <button
                    type="button"
                    @click="showNewAdminPassword = !showNewAdminPassword"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                    :aria-label="showNewAdminPassword ? 'Hide password' : 'Show password'"
                  >
                    <EyeOff v-if="showNewAdminPassword" class="w-4 h-4" />
                    <Eye v-else class="w-4 h-4" />
                  </button>
                </div>
              </div>
              <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                <div class="relative">
                  <input
                    v-model="newAdminForm.passwordConfirmation"
                    :type="showNewAdminConfirmPassword ? 'text' : 'password'"
                    class="w-full px-4 pr-11 py-3 border border-gray-300 rounded-lg"
                  />
                  <button
                    type="button"
                    @click="showNewAdminConfirmPassword = !showNewAdminConfirmPassword"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700"
                    :aria-label="showNewAdminConfirmPassword ? 'Hide password' : 'Show password'"
                  >
                    <EyeOff v-if="showNewAdminConfirmPassword" class="w-4 h-4" />
                    <Eye v-else class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>
            <button
              @click="createAdminUser"
              :disabled="adminCreateLoading"
              class="mt-4 px-6 py-3 bg-[#15803D] text-white rounded-lg hover:bg-[#15803de6] transition font-medium"
            >
              {{ adminCreateLoading ? "Creating Admin..." : "Create Admin User" }}
            </button>
          </div>

          <div class="bg-white rounded-2xl shadow-lg border border-slate-100 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Existing Admin Users</h3>
            <div v-if="adminDeleteError" class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
              {{ adminDeleteError }}
            </div>
            <div v-if="adminDeleteMessage" class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
              {{ adminDeleteMessage }}
            </div>
            <div v-if="adminUsersLoading" class="text-gray-600 text-sm">Loading admin users...</div>
            <div v-else-if="adminUsersError" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
              {{ adminUsersError }}
            </div>
            <div v-else class="space-y-2">
              <div v-for="adminItem in adminUsers" :key="adminItem.id" class="p-3 border border-gray-200 rounded-lg flex items-start justify-between gap-3">
                <div>
                  <p class="font-medium text-gray-900">
                    {{ adminItem.name }}
                    <span v-if="user && adminItem.id === user.id" class="text-xs text-blue-600">(You)</span>
                  </p>
                  <p class="text-sm text-gray-600">{{ adminItem.email }}</p>
                  <p class="text-xs text-gray-500">{{ adminItem.contactNumber || "No contact number" }}</p>
                </div>
                <button
                  @click="deleteAdminUser(adminItem)"
                  :disabled="deletingAdminId === adminItem.id || (user ? adminItem.id === user.id : false)"
                  class="inline-flex items-center gap-1 text-xs text-red-600 hover:text-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                  {{ deletingAdminId === adminItem.id ? "Deleting..." : "Delete" }}
                </button>
              </div>
            </div>
          </div>

          <button
            @click="saveSettings"
            :disabled="settingsSaving"
            class="px-6 py-3 bg-[#15803D] text-white rounded-lg hover:bg-[#15803de6] transition font-medium shadow-lg"
          >
            {{ settingsSaving ? "Saving..." : "Save Settings" }}
          </button>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuth } from "../composables/useAuth";
import { API_BASE_URL, apiRequest } from "../lib/api";
import type { Complaint, ComplaintCategory, ComplaintStats, ComplaintStatus } from "../data/mockData";
import StatusBadge from "../components/StatusBadge.vue";
import {
  Building2,
  LayoutDashboard,
  FileText,
  BarChart3,
  Settings,
  LogOut,
  Eye,
  Trash2,
  Clock,
  Search,
  CheckCircle,
  AlertCircle,
  Ban,
  Filter,
  X,
  EyeOff,
} from "lucide-vue-next";

const router = useRouter();
const route = useRoute();
const { user, logout, setUser } = useAuth();

const activeTab = ref("dashboard");
const searchQuery = ref("");
const statusFilter = ref<ComplaintStatus | "All">("All");
const categoryFilter = ref<ComplaintCategory | "All">("All");
const showFilters = ref(false);
const loading = ref(false);
const loadError = ref("");
const reportsLoading = ref(false);
const reportsError = ref("");
const settingsLoading = ref(false);
const settingsError = ref("");
const settingsSaving = ref(false);
const settingsSaveMessage = ref("");
const accountSaving = ref(false);
const accountMessage = ref("");
const accountError = ref("");
const adminUsersLoading = ref(false);
const adminUsersError = ref("");
const adminCreateLoading = ref(false);
const adminCreateMessage = ref("");
const adminCreateError = ref("");
const adminDeleteMessage = ref("");
const adminDeleteError = ref("");
const deletingAdminId = ref<number | null>(null);
const deletingComplaintId = ref<number | null>(null);
const showAccountNewPassword = ref(false);
const showAccountConfirmPassword = ref(false);
const showNewAdminPassword = ref(false);
const showNewAdminConfirmPassword = ref(false);
const complaints = ref<Complaint[]>([]);
const stats = ref<ComplaintStats>({
  total: 0,
  pending: 0,
  investigating: 0,
  resolved: 0,
  rejected: 0,
});

interface CategoryReportRow {
  category: string;
  count: number;
  percentage: number;
}

interface ReportsOverview {
  total: number;
  byCategory: CategoryReportRow[];
  statusOverview: {
    pending: number;
    investigating: number;
    resolved: number;
    rejected: number;
  };
  generatedAt: string;
}

interface SettingsPayload {
  barangayName: string;
  contactEmail: string;
  contactNumber: string;
  notifyEmailNewComplaints: boolean;
  notifySmsUrgentCases: boolean;
  notifyDailySummaryReports: boolean;
}

interface AdminUser {
  id: number;
  name: string;
  email: string;
  role: "admin";
  contactNumber?: string;
}

const reportData = ref<ReportsOverview | null>(null);
const adminUsers = ref<AdminUser[]>([]);
const settingsForm = ref<SettingsPayload>({
  barangayName: "",
  contactEmail: "",
  contactNumber: "",
  notifyEmailNewComplaints: true,
  notifySmsUrgentCases: true,
  notifyDailySummaryReports: false,
});
const accountForm = ref({
  name: "",
  email: "",
  contactNumber: "",
  newPassword: "",
  newPasswordConfirmation: "",
});
const newAdminForm = ref({
  name: "",
  email: "",
  contactNumber: "",
  password: "",
  passwordConfirmation: "",
});

onMounted(() => {
  if (!user.value || user.value.role !== "admin") {
    router.push("/admin/login");
    return;
  }
  const tab = route.query.tab;
  if (typeof tab === "string" && menuItems.some((item) => item.id === tab)) {
    activeTab.value = tab;
  }
  initializeAccountForm();
  void fetchDashboardData();
});

watch(activeTab, (tab) => {
  if (tab === "reports" && !reportData.value && !reportsLoading.value) {
    void fetchReportsData();
  }

  if (tab === "settings" && !settingsForm.value.barangayName && !settingsLoading.value) {
    void fetchSettings();
  }

  if (tab === "settings" && !adminUsersLoading.value && adminUsers.value.length === 0) {
    void fetchAdminUsers();
  }
});

const menuItems = [
  { id: "dashboard", label: "Dashboard", icon: LayoutDashboard },
  { id: "complaints", label: "Complaints", icon: FileText },
  { id: "reports", label: "Reports", icon: BarChart3 },
  { id: "settings", label: "Settings", icon: Settings },
];

const activeLabel = computed(() => menuItems.find((item) => item.id === activeTab.value)?.label ?? "");
const activeDescription = computed(() => {
  if (activeTab.value === "dashboard") return "Overview of barangay complaint management";
  if (activeTab.value === "complaints") return "Manage all complaints submitted by residents";
  if (activeTab.value === "reports") return "Generate and view system reports";
  return "Configure system settings and preferences";
});

const filteredComplaints = computed(() =>
  complaints.value.filter((complaint) => {
    const query = searchQuery.value.toLowerCase();
    const matchesSearch =
      query === "" ||
      complaint.trackingNumber.toLowerCase().includes(query) ||
      complaint.residentName.toLowerCase().includes(query) ||
      complaint.description.toLowerCase().includes(query);
    const matchesStatus = statusFilter.value === "All" || complaint.status === statusFilter.value;
    const matchesCategory = categoryFilter.value === "All" || complaint.category === categoryFilter.value;
    return matchesSearch && matchesStatus && matchesCategory;
  }),
);

const recentComplaints = computed(() => complaints.value.slice(0, 5));

const hasActiveFilters = computed(
  () => searchQuery.value !== "" || statusFilter.value !== "All" || categoryFilter.value !== "All",
);

const clearFilters = () => {
  searchQuery.value = "";
  statusFilter.value = "All";
  categoryFilter.value = "All";
};

const handleLogout = () => {
  logout();
  router.push("/admin/login");
};

const initializeAccountForm = () => {
  accountForm.value.name = user.value?.name ?? "";
  accountForm.value.email = user.value?.email ?? "";
  accountForm.value.contactNumber = user.value?.contactNumber ?? "";
  accountForm.value.newPassword = "";
  accountForm.value.newPasswordConfirmation = "";
};

const fetchDashboardData = async () => {
  loading.value = true;
  loadError.value = "";

  try {
    const [complaintsResponse, statsResponse] = await Promise.all([
      apiRequest<Complaint[]>("/complaints"),
      apiRequest<ComplaintStats>("/complaints/stats"),
    ]);
    complaints.value = complaintsResponse;
    stats.value = statsResponse;
  } catch (err) {
    loadError.value = err instanceof Error ? err.message : "Failed to load complaints.";
  } finally {
    loading.value = false;
  }
};

const fetchReportsData = async () => {
  reportsLoading.value = true;
  reportsError.value = "";

  try {
    reportData.value = await apiRequest<ReportsOverview>("/reports/overview");
  } catch (err) {
    reportsError.value = err instanceof Error ? err.message : "Failed to load reports.";
  } finally {
    reportsLoading.value = false;
  }
};

const fetchSettings = async () => {
  settingsLoading.value = true;
  settingsError.value = "";
  settingsSaveMessage.value = "";

  try {
    settingsForm.value = await apiRequest<SettingsPayload>("/settings");
  } catch (err) {
    settingsError.value = err instanceof Error ? err.message : "Failed to load settings.";
  } finally {
    settingsLoading.value = false;
  }
};

const fetchAdminUsers = async () => {
  adminUsersLoading.value = true;
  adminUsersError.value = "";

  try {
    adminUsers.value = await apiRequest<AdminUser[]>("/admin/users");
  } catch (err) {
    adminUsersError.value = err instanceof Error ? err.message : "Failed to load admin users.";
  } finally {
    adminUsersLoading.value = false;
  }
};

const saveSettings = async () => {
  settingsSaving.value = true;
  settingsError.value = "";
  settingsSaveMessage.value = "";

  try {
    const response = await apiRequest<{ message: string; settings: SettingsPayload }>("/settings", {
      method: "PUT",
      body: JSON.stringify(settingsForm.value),
    });
    settingsForm.value = response.settings;
    settingsSaveMessage.value = response.message;
  } catch (err) {
    settingsError.value = err instanceof Error ? err.message : "Failed to save settings.";
  } finally {
    settingsSaving.value = false;
  }
};

const saveAccount = async () => {
  if (!user.value) return;

  accountSaving.value = true;
  accountMessage.value = "";
  accountError.value = "";

  try {
    const payload: {
      name: string;
      email: string;
      contactNumber: string;
      newPassword?: string;
      newPassword_confirmation?: string;
    } = {
      name: accountForm.value.name,
      email: accountForm.value.email,
      contactNumber: accountForm.value.contactNumber,
    };

    if (accountForm.value.newPassword) {
      payload.newPassword = accountForm.value.newPassword;
      payload.newPassword_confirmation = accountForm.value.newPasswordConfirmation;
    }

    const response = await apiRequest<{ message: string; user: AdminUser }>(`/admin/users/${user.value.id}`, {
      method: "PATCH",
      body: JSON.stringify(payload),
    });
    setUser(response.user);
    accountMessage.value = response.message;
    initializeAccountForm();
    adminUsers.value = adminUsers.value.map((admin) => (admin.id === response.user.id ? response.user : admin));
  } catch (err) {
    accountError.value = err instanceof Error ? err.message : "Failed to update account.";
  } finally {
    accountSaving.value = false;
  }
};

const createAdminUser = async () => {
  adminCreateLoading.value = true;
  adminCreateMessage.value = "";
  adminCreateError.value = "";

  try {
    const response = await apiRequest<{ message: string; user: AdminUser }>("/admin/users", {
      method: "POST",
      body: JSON.stringify({
        name: newAdminForm.value.name,
        email: newAdminForm.value.email,
        contactNumber: newAdminForm.value.contactNumber,
        password: newAdminForm.value.password,
        password_confirmation: newAdminForm.value.passwordConfirmation,
      }),
    });
    adminUsers.value = [...adminUsers.value, response.user].sort((a, b) => a.name.localeCompare(b.name));
    adminCreateMessage.value = response.message;
    newAdminForm.value = {
      name: "",
      email: "",
      contactNumber: "",
      password: "",
      passwordConfirmation: "",
    };
  } catch (err) {
    adminCreateError.value = err instanceof Error ? err.message : "Failed to create admin user.";
  } finally {
    adminCreateLoading.value = false;
  }
};

const deleteAdminUser = async (adminUser: AdminUser) => {
  if (!user.value || deletingAdminId.value !== null) return;

  adminDeleteMessage.value = "";
  adminDeleteError.value = "";

  if (adminUser.id === user.value.id) {
    adminDeleteError.value = "You cannot delete your own active admin account.";
    return;
  }

  const confirmed = window.confirm(`Delete admin account "${adminUser.name}"?`);
  if (!confirmed) return;

  deletingAdminId.value = adminUser.id;
  try {
    const response = await apiRequest<{ message: string }>(`/admin/users/${adminUser.id}`, {
      method: "DELETE",
    });
    adminUsers.value = adminUsers.value.filter((entry) => entry.id !== adminUser.id);
    adminDeleteMessage.value = response.message;
  } catch (err) {
    adminDeleteError.value = err instanceof Error ? err.message : "Failed to delete admin account.";
  } finally {
    deletingAdminId.value = null;
  }
};

const deleteComplaint = async (complaintItem: Complaint) => {
  if (deletingComplaintId.value !== null) return;
  const confirmed = window.confirm(`Delete complaint ${complaintItem.trackingNumber}? This action cannot be undone.`);
  if (!confirmed) return;

  deletingComplaintId.value = complaintItem.id;
  loadError.value = "";

  try {
    await apiRequest<{ message: string }>(`/complaints/${complaintItem.id}`, {
      method: "DELETE",
    });
    complaints.value = complaints.value.filter((item) => item.id !== complaintItem.id);
    await fetchDashboardData();
  } catch (err) {
    loadError.value = err instanceof Error ? err.message : "Failed to delete complaint.";
  } finally {
    deletingComplaintId.value = null;
  }
};

const exportReport = (format: "pdf" | "excel" | "csv") => {
  window.open(`${API_BASE_URL}/reports/export/${format}`, "_blank", "noopener,noreferrer");
};

const formatDate = (date: string) =>
  new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
</script>
