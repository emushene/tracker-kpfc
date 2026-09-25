# Add project specific ProGuard rules here.
# By default, the flags in this file are appended to flags specified
# in getDefaultProguardFile('proguard-android-optimize.txt')

# Keep data models used by Gson/Retrofit serialization
-keepclassmembers class com.kpfc.fleet.driver.data.model.** { <fields>; }
