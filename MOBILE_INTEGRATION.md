# PackRelay — React Native Integration Guide

This guide shows how to integrate PackRelay with a React Native app (Expo or bare workflow) to submit WPForms entries from your mobile app.

## Prerequisites

- PackRelay installed and configured on your WordPress site
- Firebase project with App Check enabled
- WPForms form ID and field IDs

## 1. Install Firebase App Check for React Native

For bare React Native projects:

```bash
npm install @react-native-firebase/app @react-native-firebase/app-check
```

For Expo projects (with config plugin):

```bash
npx expo install @react-native-firebase/app @react-native-firebase/app-check
```

Follow the [React Native Firebase setup guide](https://rnfirebase.io/) for platform-specific configuration (GoogleService-Info.plist for iOS, google-services.json for Android).

## 2. App Check Token Generation

### Initialize App Check

```tsx
// app/firebaseSetup.ts
import { firebase } from '@react-native-firebase/app-check';

export async function initAppCheck() {
  const rnfbProvider = firebase.appCheck().newReactNativeFirebaseAppCheckProvider();
  rnfbProvider.configure({
    android: {
      provider: __DEV__ ? 'debug' : 'playIntegrity',
    },
    apple: {
      provider: __DEV__ ? 'debug' : 'appAttest',
    },
  });

  await firebase.appCheck().initializeAppCheck({
    provider: rnfbProvider,
    isTokenAutoRefreshEnabled: true,
  });
}
```

### Get App Check Token

```tsx
// hooks/useAppCheckToken.ts
import { firebase } from '@react-native-firebase/app-check';

export async function getAppCheckToken(): Promise<string> {
  const { token } = await firebase.appCheck().getToken(true);
  return token;
}
```

## 3. Fetching Form Fields

Before rendering your form, fetch the field structure from PackRelay:

```tsx
// api/packrelay.ts
const API_BASE = 'https://yoursite.com/wp-json/packrelay/v1';

export interface FormField {
  id: string;
  type: string;
  label: string;
  required: boolean;
}

export interface FormStructure {
  success: boolean;
  form_id: number;
  form_title: string;
  fields: FormField[];
}

export async function getFormFields(formId: number): Promise<FormStructure> {
  const response = await fetch(`${API_BASE}/forms/${formId}/fields`);
  if (!response.ok) {
    throw new Error(`Failed to fetch form fields: ${response.status}`);
  }
  return response.json();
}
```

## 4. Submitting Forms

```tsx
// api/packrelay.ts (continued)

export interface SubmitResult {
  success: boolean;
  message: string;
  entry_id?: number;
  code?: string;
}

export async function submitForm(
  formId: number,
  fields: Record<string, string>,
  appCheckToken: string
): Promise<SubmitResult> {
  const response = await fetch(`${API_BASE}/submit/${formId}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      fields,
      app_check_token: appCheckToken,
    }),
  });

  if (!response.ok) {
    const errorBody = await response.json().catch(() => null);
    throw new Error(
      errorBody?.message || `Form submission failed: ${response.status}`
    );
  }

  return response.json();
}
```

## 5. Complete Form Component Example

```tsx
// screens/ContactForm.tsx
import React, { useState, useEffect } from 'react';
import {
  View,
  Text,
  TextInput,
  TouchableOpacity,
  Alert,
  ScrollView,
  ActivityIndicator,
  StyleSheet,
} from 'react-native';
import { getAppCheckToken } from '../hooks/useAppCheckToken';
import { getFormFields, submitForm, FormField } from '../api/packrelay';

const FORM_ID = 123; // Your WPForms form ID

export function ContactForm() {
  const [fields, setFields] = useState<FormField[]>([]);
  const [values, setValues] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    loadForm();
  }, []);

  async function loadForm() {
    try {
      const data = await getFormFields(FORM_ID);
      setFields(data.fields);
    } catch (error) {
      Alert.alert('Error', 'Failed to load form. Please try again.');
    } finally {
      setLoading(false);
    }
  }

  function handleChange(fieldId: string, value: string) {
    setValues(prev => ({ ...prev, [fieldId]: value }));
  }

  async function handleSubmit() {
    // Validate required fields
    for (const field of fields) {
      if (field.required && !values[field.id]?.trim()) {
        Alert.alert('Error', `${field.label} is required.`);
        return;
      }
    }

    setSubmitting(true);

    try {
      const token = await getAppCheckToken();
      const result = await submitForm(FORM_ID, values, token);
      if (result.success) {
        Alert.alert('Success', result.message);
        setValues({});
      } else {
        Alert.alert('Error', result.message || 'Submission failed.');
      }
    } catch (error) {
      Alert.alert('Error', 'Submission failed. Please try again.');
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) {
    return (
      <View style={styles.center}>
        <ActivityIndicator size="large" />
      </View>
    );
  }

  return (
    <ScrollView style={styles.container}>
      {fields.map(field => (
        <View key={field.id} style={styles.fieldContainer}>
          <Text style={styles.label}>
            {field.label}
            {field.required && <Text style={styles.required}> *</Text>}
          </Text>
          <TextInput
            style={[styles.input, field.type === 'textarea' && styles.textarea]}
            value={values[field.id] || ''}
            onChangeText={text => handleChange(field.id, text)}
            multiline={field.type === 'textarea'}
            numberOfLines={field.type === 'textarea' ? 4 : 1}
            keyboardType={field.type === 'email' ? 'email-address' : field.type === 'phone' ? 'phone-pad' : 'default'}
            autoCapitalize={field.type === 'email' ? 'none' : 'sentences'}
            editable={!submitting}
          />
        </View>
      ))}

      <TouchableOpacity
        style={[styles.button, submitting && styles.buttonDisabled]}
        onPress={handleSubmit}
        disabled={submitting}
      >
        {submitting ? (
          <ActivityIndicator color="#fff" />
        ) : (
          <Text style={styles.buttonText}>Submit</Text>
        )}
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, padding: 16 },
  center: { flex: 1, justifyContent: 'center', alignItems: 'center' },
  fieldContainer: { marginBottom: 16 },
  label: { fontSize: 16, fontWeight: '600', marginBottom: 4 },
  required: { color: '#e53e3e' },
  input: {
    borderWidth: 1,
    borderColor: '#d1d5db',
    borderRadius: 8,
    padding: 12,
    fontSize: 16,
  },
  textarea: { minHeight: 100, textAlignVertical: 'top' },
  button: {
    backgroundColor: '#3b82f6',
    padding: 16,
    borderRadius: 8,
    alignItems: 'center',
    marginTop: 8,
    marginBottom: 32,
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontSize: 18, fontWeight: '600' },
});
```

## 6. Error Handling

PackRelay returns structured error responses:

| Status | Code | What to do |
|--------|------|------------|
| 400 | `missing_fields` | Check required fields are filled |
| 400 | `invalid_email` | Validate email format before sending |
| 403 | `appcheck_missing` | Ensure App Check token is included in the request |
| 403 | `appcheck_failed` | Token is invalid or expired — refresh and retry |
| 404 | `form_not_found` | Check form ID is correct and in the allowlist |
| 500 | `entry_failed` | Server-side issue — show generic error |

## 7. CORS Configuration

In your PackRelay settings, add your app's origins:

- **Expo Go**: `http://localhost:8081`
- **Capacitor**: `capacitor://localhost`
- **Production web**: `https://your-app-domain.com`

For React Native apps making requests from native code (not a WebView), CORS headers may not be enforced by the client. However, it's still good practice to configure allowed origins.

## 8. Security Best Practices

- App Check tokens are automatically managed by the Firebase SDK — no secret keys in client code
- Use HTTPS for all API calls
- Validate input on the client side before sending
- App Check tokens are short-lived and auto-refreshed by the SDK
- Consider adding request timeouts to prevent hanging requests
- For development/testing, use the Firebase App Check debug provider
