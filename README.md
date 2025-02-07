# AutoReceipt System

This is a Fee Management System in university setting designed to handle payments via M-Pesa STK Push and PayPal API. its also allows sharing of accounts between parents and students The system allows users to manage and make payments for various services, providing an easy, secure, and reliable method to handle fees. 

## Features

- **M-Pesa STK Push Integration**: Users can pay directly through M-Pesa using the STK Push API, offering a seamless and user-friendly mobile payment option.
- **PayPal API Integration**: Supports PayPal payments for users who prefer to pay via their PayPal accounts. 
- **Auto-Receipt System**: After every successful transaction, the system automatically generates and sends a receipt to the user, confirming their payment. This ensures a smooth and automated record-keeping process for both users and administrators.
- **Transparency**: The system allows parents and students to share accounts
## Prerequisites

Before running this system, ensure you have the following:

- An active M-Pesa STK Push API account.
- A registered PayPal developer account for API integration.
- A server to host the system (with proper permissions and internet connectivity) also you can use Ngrok to tunnel the localhost for proper working and testing APIs.
  
## Installation

1. Clone the repository to your local machine.
    ```bash
    git clone https://github.com/your-username/fee-management-system.git
    ```

2. Navigate into the project directory.
    ```bash
    cd fee-management-system
    ```

3. Install the required dependencies.
    ```bash
    pip install -r requirements.txt
    ```

4. Set up your environment variables for M-Pesa and PayPal API keys in a `.env` file:
    - `MPESA_API_KEY=<your_m_pesa_api_key>`
    - `PAYPAL_CLIENT_ID=<your_paypal_client_id>`
    - `PAYPAL_SECRET=<your_paypal_secret>`

## Usage

### M-Pesa STK Push

To initiate an M-Pesa STK Push request, the system will prompt the user to enter their mobile number and the amount they wish to pay. The user will receive a prompt on their phone to approve the payment. Upon approval, the system verifies the payment and generates a receipt.

### PayPal Payment

Users who prefer to pay through PayPal can simply log in to their PayPal account within the system interface and approve the payment. The PayPal API handles the transaction, and a receipt is generated for the user once the payment is completed.

### Auto-Receipt System

Once a payment is successfully processed (either via M-Pesa or PayPal), the system automatically generates a digital receipt that includes:
- Payment ID
- Transaction amount
- Payment method (M-Pesa or PayPal)
- Timestamp of the transaction
- A unique receipt number

The receipt is then sent via email (or another communication method, depending on configuration) to the user.

## Example

1. **Initiate Payment:**
    - User selects the payment method (M-Pesa or PayPal).
    - System requests the user’s payment details (mobile number for M-Pesa, or PayPal login credentials).

2. **Process Payment:**
    - The payment request is sent to M-Pesa or PayPal API.
    - Upon successful transaction, a confirmation is received.

3. **Generate Auto-Receipt:**
    - Receipt is automatically generated and sent to the user.
    - The receipt contains details of the payment for the user’s records.

## Contributing

If you would like to contribute to this project, feel free to fork the repository and submit a pull request with your changes. Please ensure that your code adheres to the project's coding standards and includes necessary tests where applicable.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Acknowledgements

- M-Pesa STK Push API for mobile payments.
- PayPal API for handling online payments.
